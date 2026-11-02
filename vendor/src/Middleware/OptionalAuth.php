<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Repository\TokenRepository;

/**
 * Menempel user bila Bearer valid. Tanpa token, permintaan tetap jalan sebagai publik.
 */
final class OptionalAuth implements Middleware
{
    public function __construct(private readonly TokenRepository $tokens)
    {
    }

    public function handle(Request $request): ?Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return null;
        }

        $user = $this->tokens->findUserByAccessToken($token);

        if ($user === null) {
            return null;
        }

        $request->setAttribute('user', $user);
        $request->setAttribute('token', $token);

        return null;
    }
}
