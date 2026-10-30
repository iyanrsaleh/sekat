<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Model;

/**
 * Token API berdiri sendiri (klien), nama + masa berlaku.
 * Mirip konsep middleware pada referensi: rahasia NX_, disimpan sebagai hash.
 */
final class ApiToken extends Model
{
    protected static string $table = 'api_tokens';

    protected static bool $exposed = false;

    protected static array $fields = [
        'name' => ['type' => 'string', 'max' => 60, 'required' => true, 'unique' => true, 'label' => 'Nama'],
        'token_hash' => ['type' => 'string', 'max' => 64, 'required' => true, 'unique' => true, 'label' => 'Token'],
        'expires_at' => ['type' => 'datetime', 'required' => false, 'label' => 'Kedaluwarsa'],
    ];
}