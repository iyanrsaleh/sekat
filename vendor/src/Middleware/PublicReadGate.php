<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Repository\ApiTokenRepository;
use App\Repository\SettingRepository;

/**
 * Satu sakelar menutup pembacaan API situs dari luar.
 * Halaman situs tetap boleh mengambil data lewat fetch.
 * Token API yang valid juga lolos, tanpa grant per resource.
 */
final class PublicReadGate implements Middleware
{
    public function __construct(
        private readonly SettingRepository $settings,
        private readonly ApiTokenRepository $tokens,
    ) {
    }

    public function handle(Request $request): ?Response
    {
        $closed = $this->settings->publicApiLock();

        if ($closed === null) {
            $closed = false;
            $this->settings->savePublicApiLock(false);
        }

        if (!$closed) {
            return null;
        }

        $secret = $request->bearerToken();
        $name = $secret !== null ? $this->tokens->valid($secret) : null;

        if ($name !== null) {
            $request->setAttribute('client', $name);

            return null;
        }

        if ($this->pageFetch($request)) {
            return null;
        }

        return Response::error('UNAUTHORIZED', 'API situs tertutup dari luar', 401)
            ->withHeader('Cache-Control', 'no-store');
    }

    private function pageFetch(Request $request): bool
    {
        $dest = strtolower((string) $request->header('Sec-Fetch-Dest'));
        $mode = strtolower((string) $request->header('Sec-Fetch-Mode'));
        $site = strtolower((string) $request->header('Sec-Fetch-Site'));

        if ($dest === 'document' || $mode === 'navigate') {
            return false;
        }

        if ($site === 'same-origin' || $site === 'same-site') {
            return true;
        }

        return $this->fromThisHost($request);
    }

    private function fromThisHost(Request $request): bool
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));

        if ($host === '') {
            return false;
        }

        foreach (['Referer', 'Origin'] as $name) {
            $value = trim((string) $request->header($name));

            if ($value === '') {
                continue;
            }

            $parts = parse_url($value);

            if (!is_array($parts) || !isset($parts['host'])) {
                continue;
            }

            $origin = strtolower((string) $parts['host']);
            $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

            if ($origin . $port === $host || $origin === $host) {
                return true;
            }
        }

        return false;
    }
}
