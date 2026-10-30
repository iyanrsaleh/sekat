<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Model;

/**
 * Catatan aktivitas sensitif (login, register, logout).
 * Tidak bisa ditulis lewat API; hanya dibaca admin.
 */
final class AuditLog extends Model
{
    protected static string $table = 'audit_logs';

    protected static bool $exposed = false;

    protected static array $fields = [
        'action' => ['type' => 'string', 'max' => 50, 'required' => true, 'label' => 'Aksi'],
        'user_id' => ['type' => 'int', 'required' => false, 'label' => 'User'],
        'ip' => ['type' => 'string', 'max' => 45, 'required' => false, 'label' => 'IP'],
        'meta' => ['type' => 'text', 'required' => false, 'label' => 'Keterangan'],
    ];
}