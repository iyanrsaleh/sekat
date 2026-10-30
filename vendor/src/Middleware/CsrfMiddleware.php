<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Perlindungan CSRF double-submit.
 *
 * Berlaku hanya bila klien memakai cookie (ada cookie XSRF-TOKEN):
 * cookie harus sama dengan header X-XSRF-TOKEN. Request dengan Bearer
 * token (bukan cookie) dilewati, karena tidak rawan CSRF.
 */
final class CsrfMiddleware implements Middleware
{
    private const COOKIE = 'XSRF-TOKEN';
    private const HEADER = 'X-XSRF-TOKEN';

    public function handle(Request $request): ?Response
    {
        if ($request->bearerToken() !== null) {
            return null;
        }

        $cookie = $request->cookie(self::COOKIE);
        $header = $request->header(self::HEADER);

        if (!is_string($cookie) || $cookie === '') {
            return null;
        }

        if (!is_string($header) || !hash_equals($cookie, $header)) {
            throw new HttpException(403, 'TOKEN_MISMATCH', 'Token CSRF tidak cocok');
        }

        return null;
    }
}