<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Model;

/**
 * Tabel users tidak diekspos sebagai /api/users.
 * Akses hanya lewat AuthController (login) dan seed.
 */
final class User extends Model
{
    protected static string $table = 'users';

    protected static bool $exposed = false;

    protected static array $hidden = ['password'];

    protected static array $fields = [
        'name' => ['type' => 'string', 'max' => 120, 'required' => true, 'label' => 'Nama'],
        'email' => ['type' => 'string', 'max' => 190, 'required' => true, 'unique' => true, 'label' => 'Email'],
        'password' => ['type' => 'string', 'max' => 255, 'required' => true, 'label' => 'Password'],
        'role' => ['type' => 'string', 'max' => 20, 'required' => true, 'default' => 'user', 'label' => 'Role'],
        'email_verified_at' => ['type' => 'datetime', 'required' => false, 'label' => 'Email terverifikasi'],
        'avatar' => ['type' => 'string', 'max' => 255, 'required' => false, 'label' => 'Foto profil'],
        'provider' => ['type' => 'string', 'max' => 20, 'required' => true, 'default' => 'local', 'label' => 'Cara masuk'],
    ];
}