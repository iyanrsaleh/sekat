<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Repository\ApiTokenRepository;

/**
 * Menerima Bearer dari token API (bukan user). Menuntut grant tertentu.
 *
 * Contoh: new ApiTokenMiddleware($repo, 'api', 'posts') — token harus punya
 * grant api/posts, selain itu 401/403.
 */
final class ApiTokenMiddleware implements Middleware
{
    public function __construct(
        private readonly ApiTokenRepository $tokens,
        private readonly string $kind = 'api',
        private readonly string $resource = '',
    ) {
    }

    public function handle(Request $request): ?Response
    {
        $secret = $request->bearerToken();

        if ($secret === null) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Token tidak ditemukan');
        }

        $name = $this->tokens->valid($secret);

        if ($name === null) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Token tidak valid atau kedaluwarsa');
        }

        if ($this->resource !== '' && !$this->tokens->hasGrant($name, $this->kind, $this->resource)) {
            throw new HttpException(403, 'FORBIDDEN', 'Token tidak punya hak untuk resource ini');
        }

        $request->setAttribute('client', $name);

        return null;
    }
}