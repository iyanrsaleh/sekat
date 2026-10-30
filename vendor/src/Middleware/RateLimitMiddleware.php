<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;

/**
 * Membatasi jumlah request per IP + path.
 * Dipakai pada endpoint sensitif (login, register).
 */
final class RateLimitMiddleware implements Middleware
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int $max = 10,
        private readonly int $windowSeconds = 60,
    ) {
    }

    public function handle(Request $request): ?Response
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = $ip . '|' . $request->path();

        if (!$this->limiter->allow($key, $this->max, $this->windowSeconds)) {
            throw new HttpException(
                429,
                'TOO_MANY_REQUESTS',
                'Terlalu banyak percobaan. Coba lagi dalam ' . $this->windowSeconds . ' detik.',
            );
        }

        return null;
    }
}