<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Repository\TokenRepository;

/**
 * Membaca header "Authorization: Bearer <token>".
 * Bila valid, data user ditempel ke request dengan key "user".
 */
final class AuthMiddleware implements Middleware
{
    public function __construct(private readonly TokenRepository $tokens)
    {
    }

    public function handle(Request $request): ?Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Token tidak ditemukan');
        }

        $user = $this->tokens->findUserByAccessToken($token);

        if ($user === null) {
            throw new HttpException(401, 'UNAUTHORIZED', 'Token tidak valid atau kedaluwarsa');
        }

        $request->setAttribute('user', $user);
        $request->setAttribute('token', $token);

        return null;
    }
}