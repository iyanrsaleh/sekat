<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class EmailTokenRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return array{token: string}
     */
    public function issue(int $userId, string $purpose, int $minutes): array
    {
        $token = 'NX_' . bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable('+' . $minutes . ' minutes'))->format('Y-m-d H:i:s');

        $this->database->execute(
            'INSERT INTO email_tokens (user_id, token_hash, purpose, expires_at) VALUES (:user_id, :token_hash, :purpose, :expires_at)',
            [
                'user_id' => $userId,
                'token_hash' => hash('sha256', $token),
                'purpose' => $purpose,
                'expires_at' => $expiresAt,
            ],
        );

        return ['token' => $token];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findValid(string $token, string $purpose): ?array
    {
        return $this->database->first(
            'SELECT id, user_id FROM email_tokens
             WHERE token_hash = :token_hash AND purpose = :purpose AND used_at IS NULL AND expires_at > :now
             LIMIT 1',
            ['token_hash' => hash('sha256', $token), 'purpose' => $purpose, 'now' => date('Y-m-d H:i:s')],
        );
    }

    public function consume(int $id): void
    {
        $this->database->execute(
            'UPDATE email_tokens SET used_at = NOW() WHERE id = :id',
            ['id' => $id],
        );
    }

    public function purgeExpired(): void
    {
        $this->database->execute(
            'DELETE FROM email_tokens WHERE expires_at <= :now OR used_at IS NOT NULL',
            ['now' => date('Y-m-d H:i:s')],
        );
    }
}