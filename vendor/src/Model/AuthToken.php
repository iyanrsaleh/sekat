<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Model;

/**
 * Token Bearer. Yang disimpan hanya hash SHA-256, bukan token aslinya.
 */
final class AuthToken extends Model
{
    protected static string $table = 'auth_tokens';

    protected static bool $exposed = false;

    protected static array $fields = [
        'user_id' => ['type' => 'int', 'required' => true, 'label' => 'User'],
        'token_hash' => ['type' => 'string', 'max' => 64, 'required' => true, 'unique' => true, 'label' => 'Token'],
        'expires_at' => ['type' => 'datetime', 'required' => true, 'label' => 'Kedaluwarsa'],
        'type' => ['type' => 'string', 'max' => 10, 'required' => true, 'default' => 'access', 'label' => 'Tipe'],
    ];
}