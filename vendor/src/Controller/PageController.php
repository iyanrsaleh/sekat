<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Partials;
use App\Core\Request;
use App\Core\Response;

final class PageController
{
    private static function publicDir(): string
    {
        $public = getenv('FRAMEWORK_PUBLIC');

        if (is_string($public) && $public !== '') {
            return rtrim(str_replace('\\', '/', $public), '/');
        }

        return str_replace('\\', '/', __DIR__ . '/../../../public');
    }

    public function home(): Response
    {
        return $this->show(['page' => 'index']);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params): Response
    {
        $page = (string) ($params['page'] ?? 'index');
        $page = (string) preg_replace('/[^a-z0-9_-]/i', '', $page);

        if ($page === '') {
            return Response::error('PAGE_NOT_FOUND', 'Halaman tidak ditemukan', 404);
        }

        $path = self::publicDir() . '/' . $page . '.html';

        if (!is_file($path)) {
            return $this->notFound();
        }

        return Response::html($this->prepare((string) file_get_contents($path)));
    }

    private function notFound(): Response
    {
        $fallback = self::publicDir() . '/404.html';

        if (!is_file($fallback)) {
            return Response::error('PAGE_NOT_FOUND', 'Halaman tidak ditemukan', 404);
        }

        return Response::html($this->prepare((string) file_get_contents($fallback)), 404);
    }

    private function prepare(string $html): string
    {
        $html = $this->applyTitle($this->withBase(Partials::render($this->applyGoogle($html), self::publicDir())));

        return str_replace('__APP_ORIGIN__', htmlspecialchars(Request::appOrigin(), ENT_QUOTES), $html);
    }

    private function applyGoogle(string $html): string
    {
        if (!str_contains($html, '__GOOGLE_CLIENT_ID__')) {
            return $html;
        }

        $oauth = require dirname(__DIR__, 2) . '/config/oauth.php';
        $clientId = (string) ($oauth['google']['client_id'] ?? '');

        return str_replace('__GOOGLE_CLIENT_ID__', htmlspecialchars($clientId, ENT_QUOTES), $html);
    }

    private function applyTitle(string $html): string
    {
        $site = \App\Core\Settings::siteTitle();

        if ($site === '') {
            return $html;
        }

        return (string) preg_replace_callback('/<title>(.*?)<\/title>/is', static function (array $m) use ($site): string {
            $current = trim($m[1]);

            if ($current === '') {
                return '<title>' . htmlspecialchars($site, ENT_QUOTES) . '</title>';
            }

            if (str_contains($current, $site)) {
                return $m[0];
            }

            return '<title>' . $current . ' — ' . htmlspecialchars($site, ENT_QUOTES) . '</title>';
        }, $html, 1);
    }

    private function withBase(string $html): string
    {
        $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
        $base = ($base === '' ? '' : $base) . '/';

        if (stripos($html, '<base ') !== false) {
            return $html;
        }

        return (string) preg_replace(
            '/<head>/i',
            '<head><base href="' . htmlspecialchars($base, ENT_QUOTES) . '">',
            $html,
            1
        );
    }
}