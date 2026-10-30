<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\HttpException;

/**
 * Token API nama + masa berlaku + grant (menyerupai middleware pada referensi).
 */
final class ApiTokenRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Membuat token. expires null berarti tidak kedaluwarsa.
     *
     * @return array{name: string, token: string, expires: string|null}
     */
    public function create(string $name, ?string $secret, ?\DateTimeImmutable $expires): array
    {
        $name = strtolower(trim($name));

        if ($name === '' || preg_match('/^[a-z0-9_\-\.]+$/', $name) !== 1) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Nama token hanya huruf kecil, angka, `_`, `-`, `.`');
        }

        if ($this->exists($name)) {
            throw new HttpException(422, 'NAME_TAKEN', 'Nama token sudah ada');
        }

        if ($secret === null || trim($secret) === '') {
            $secret = 'NX_' . bin2hex(random_bytes(8));
        } elseif (!str_starts_with($secret, 'NX_')) {
            $secret = 'NX_' . trim($secret);
        }

        if (strlen($secret) < 8) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Token minimal 8 karakter');
        }

        $this->database->execute(
            'INSERT INTO api_tokens (name, token_hash, expires_at) VALUES (:name, :token_hash, :expires_at)',
            [
                'name' => $name,
                'token_hash' => hash('sha256', $secret),
                'expires_at' => $expires?->format('Y-m-d H:i:s'),
            ],
        );

        if ($this->count() === 1) {
            $this->grant($name, 'middleware', 'middleware');
        }

        return [
            'name' => $name,
            'token' => $secret,
            'expires' => $expires?->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByToken(string $secret): ?array
    {
        $hash = hash('sha256', $secret);

        return $this->database->first(
            'SELECT id, name, expires_at FROM api_tokens WHERE token_hash = :hash',
            ['hash' => $hash],
        );
    }

    public function valid(string $secret): ?string
    {
        $row = $this->findByToken($secret);

        if ($row === null) {
            return null;
        }

        $expires = $row['expires_at'];

        if ($expires !== null && strtotime((string) $expires) < time()) {
            return null;
        }

        return (string) $row['name'];
    }

    public function exists(string $name): bool
    {
        return $this->database->first(
            'SELECT id FROM api_tokens WHERE name = :name',
            ['name' => strtolower(trim($name))],
        ) !== null;
    }

    public function count(): int
    {
        return (int) ($this->database->first('SELECT COUNT(*) AS total FROM api_tokens')['total'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->database->select(
            'SELECT name, expires_at, created_at FROM api_tokens ORDER BY id ASC'
        );
    }

    public function revoke(string $name): bool
    {
        $name = strtolower(trim($name));

        $deleted = $this->database->execute('DELETE FROM api_tokens WHERE name = :name', ['name' => $name]);

        if ($deleted > 0) {
            $this->database->execute('DELETE FROM api_grants WHERE token_name = :name', ['name' => $name]);
        }

        return $deleted > 0;
    }

    public function grant(string $name, string $kind, string $resource): void
    {
        $name = strtolower(trim($name));

        if (!$this->exists($name)) {
            throw new HttpException(422, 'TOKEN_NOT_FOUND', 'Nama token tidak ada');
        }

        if ($this->hasGrant($name, $kind, $resource)) {
            return;
        }

        $this->database->execute(
            'INSERT INTO api_grants (token_name, kind, name) VALUES (:name, :kind, :resource)',
            ['name' => $name, 'kind' => $kind, 'resource' => $resource],
        );
    }

    public function revokeGrant(string $name, string $kind, string $resource): bool
    {
        return $this->database->execute(
            'DELETE FROM api_grants WHERE token_name = :name AND kind = :kind AND name = :resource',
            ['name' => strtolower(trim($name)), 'kind' => $kind, 'resource' => $resource],
        ) > 0;
    }

    public function hasGrant(string $name, string $kind, string $resource): bool
    {
        return $this->database->first(
            'SELECT id FROM api_grants WHERE token_name = :name AND kind = :kind AND name = :resource',
            ['name' => strtolower(trim($name)), 'kind' => $kind, 'resource' => $resource],
        ) !== null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function grants(): array
    {
        return $this->database->select('SELECT token_name, kind, name FROM api_grants ORDER BY token_name');
    }
}