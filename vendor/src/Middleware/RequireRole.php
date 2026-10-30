<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Memastikan user yang sudah lolos AuthMiddleware punya role tertentu.
 * Dipasang setelah AuthMiddleware.
 */
final class RequireRole implements Middleware
{
    public function __construct(private readonly string $role)
    {
    }

    public function handle(Request $request): ?Response
    {
        $user = $request->attribute('user');
        $role = is_array($user) ? (string) ($user['role'] ?? '') : '';

        if ($role !== $this->role) {
            throw new HttpException(403, 'FORBIDDEN', 'Butuh role ' . $this->role);
        }

        return null;
    }
}