<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Role;

final class UserRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->database->first(
            'SELECT id, name, email, password, role, avatar, provider FROM users WHERE email = :email LIMIT 1',
            ['email' => strtolower(trim($email))],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $name, string $email, string $password, string $role = Role::USER, string $provider = 'local'): array
    {
        $email = strtolower(trim($email));

        if (!Role::known($role)) {
            throw new HttpException(422, 'ROLE_UNKNOWN', 'Role tidak dikenal');
        }

        if ($this->findByEmail($email) !== null) {
            throw new HttpException(422, 'EMAIL_TAKEN', 'Email sudah terdaftar');
        }

        $this->database->execute(
            'INSERT INTO users (name, email, password, role, provider) VALUES (:name, :email, :password, :role, :provider)',
            [
                'name' => trim($name),
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'provider' => $provider === 'google' ? 'google' : 'local',
            ],
        );

        return [
            'id' => $this->database->lastInsertId(),
            'name' => trim($name),
            'email' => $email,
            'role' => $role,
            'provider' => $provider === 'google' ? 'google' : 'local',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->database->first(
            'SELECT id, name, email, role, avatar, provider FROM users WHERE id = :id',
            ['id' => $id],
        );
    }

    public function markProvider(int $id, string $provider): void
    {
        $this->database->execute(
            'UPDATE users SET provider = :provider WHERE id = :id',
            ['provider' => $provider === 'google' ? 'google' : 'local', 'id' => $id],
        );
    }

    public function updatePassword(int $id, string $password): void
    {
        $this->database->execute(
            'UPDATE users SET password = :password WHERE id = :id',
            ['password' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id],
        );
    }

    public function markEmailVerified(int $id): void
    {
        $this->database->execute(
            'UPDATE users SET email_verified_at = NOW() WHERE id = :id AND email_verified_at IS NULL',
            ['id' => $id],
        );
    }

    public function passwordHash(int $id): ?string
    {
        $row = $this->database->first('SELECT password FROM users WHERE id = :id', ['id' => $id]);

        return $row === null ? null : (string) $row['password'];
    }

    public function setAvatar(int $id, string $url): void
    {
        $this->database->execute('UPDATE users SET avatar = :avatar WHERE id = :id', [
            'avatar' => $url,
            'id' => $id,
        ]);
    }

    public function updateProfile(int $id, string $name, string $email): void
    {
        $this->database->execute('UPDATE users SET name = :name, email = :email WHERE id = :id', [
            'name' => trim($name),
            'email' => strtolower(trim($email)),
            'id' => $id,
        ]);
    }

    public function emailTakenByOther(string $email, int $exceptId): bool
    {
        return $this->database->first(
            'SELECT id FROM users WHERE email = :email AND id <> :id',
            ['email' => strtolower(trim($email)), 'id' => $exceptId],
        ) !== null;
    }

    public function count(): int
    {
        $row = $this->database->first('SELECT COUNT(*) AS total FROM users');

        return (int) ($row['total'] ?? 0);
    }
}