<?php

declare(strict_types=1);

namespace App\Core;

interface Middleware
{
    /**
     * Kembalikan Response untuk menghentikan request, atau null untuk lanjut.
     */
    public function handle(Request $request): ?Response;
}