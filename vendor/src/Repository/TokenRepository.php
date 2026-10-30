<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class TokenRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return array{token: string, expires_at: string}
     */
    public function issue(int $userId, string $type, int $days): array
    {
        $token = 'NX_' . bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable('+' . $days . ' days'))->format('Y-m-d H:i:s');

        $this->database->execute(
            'INSERT INTO auth_tokens (user_id, token_hash, expires_at, type) VALUES (:user_id, :token_hash, :expires_at, :type)',
            [
                'user_id' => $userId,
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expiresAt,
                'type' => $type,
            ],
        );

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findUserByAccessToken(string $token): ?array
    {
        return $this->findUserByToken($token, 'access');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findUserByRefreshToken(string $token): ?array
    {
        return $this->findUserByToken($token, 'refresh');
    }

    public function revoke(string $token): void
    {
        $this->database->execute(
            'DELETE FROM auth_tokens WHERE token_hash = :token_hash',
            ['token_hash' => hash('sha256', $token)],
        );
    }

    public function revokeAllForUser(int $userId): void
    {
        $this->database->execute('DELETE FROM auth_tokens WHERE user_id = :user_id', ['user_id' => $userId]);
    }

    public function purgeExpired(): void
    {
        $this->database->execute(
            'DELETE FROM auth_tokens WHERE expires_at <= :now',
            ['now' => date('Y-m-d H:i:s')],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findUserByToken(string $token, string $type): ?array
    {
        return $this->database->first(
            'SELECT u.id, u.name, u.email, u.role, u.avatar, u.provider
             FROM auth_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = :token_hash AND t.type = :type AND t.expires_at > :now
             LIMIT 1',
            ['token_hash' => hash('sha256', $token), 'type' => $type, 'now' => date('Y-m-d H:i:s')],
        );
    }
}