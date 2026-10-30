<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Model;

/**
 * Hak akses token API ke resource (kind + name), menyerupai grant middleware.
 */
final class ApiGrant extends Model
{
    protected static string $table = 'api_grants';

    protected static bool $exposed = false;

    protected static array $fields = [
        'token_name' => ['type' => 'string', 'max' => 60, 'required' => true, 'label' => 'Token'],
        'kind' => ['type' => 'string', 'max' => 20, 'required' => true, 'label' => 'Jenis'],
        'name' => ['type' => 'string', 'max' => 60, 'required' => true, 'label' => 'Resource'],
    ];
}