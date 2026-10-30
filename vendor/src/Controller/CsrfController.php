<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Response;

/**
 * Mengeluarkan token CSRF (double-submit) dan menyimpannya di cookie.
 */
final class CsrfController
{
    public function token(): Response
    {
        $token = bin2hex(random_bytes(16));

        // Tidak httpOnly karena dibaca JavaScript untuk dikirim sebagai header.
        setcookie('XSRF-TOKEN', $token, 0, '/', '', false, false);

        return Response::json(['token' => $token]);
    }
}