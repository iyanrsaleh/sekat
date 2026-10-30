<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Model;

/**
 * Token verifikasi email dan reset password.
 * Hanya hash yang disimpan; token asli hanya dikirim lewat email (atau debug).
 */
final class EmailToken extends Model
{
    protected static string $table = 'email_tokens';

    protected static bool $exposed = false;

    protected static array $fields = [
        'user_id' => ['type' => 'int', 'required' => true, 'label' => 'User'],
        'token_hash' => ['type' => 'string', 'max' => 64, 'required' => true, 'unique' => true, 'label' => 'Token'],
        'purpose' => ['type' => 'string', 'max' => 20, 'required' => true, 'label' => 'Tujuan'],
        'expires_at' => ['type' => 'datetime', 'required' => true, 'label' => 'Kedaluwarsa'],
        'used_at' => ['type' => 'datetime', 'required' => false, 'label' => 'Dipakai'],
    ];
}