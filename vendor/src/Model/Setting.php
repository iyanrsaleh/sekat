<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Model;

/**
 * Pengaturan dasar aplikasi (key/value), bukan rahasia.
 */
final class Setting extends Model
{
    protected static string $table = 'settings';

    protected static bool $exposed = false;

    protected static array $fields = [
        'key' => ['type' => 'string', 'max' => 60, 'required' => true, 'unique' => true, 'label' => 'Kunci'],
        'value' => ['type' => 'text', 'required' => false, 'label' => 'Nilai'],
    ];
}