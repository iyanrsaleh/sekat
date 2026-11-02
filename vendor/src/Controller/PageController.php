<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\HtmlPages;
use App\Core\OpenGraph;
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

    public function home(array $params, Request $request): Response
    {
        return $this->show(['page' => 'index'], $request);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params, Request $request): Response
    {
        $found = HtmlPages::locate(self::publicDir(), self::urlPath($params));

        if ($found === null) {
            return $this->notFound();
        }

        return Response::html($this->prepare((string) file_get_contents($found['file']), $found['directory']));
    }

    public function has(string $urlPath): bool
    {
        return HtmlPages::locate(self::publicDir(), $urlPath) !== null;
    }

    public function html(string $html): Response
    {
        return Response::html($this->prepare($html));
    }

    public function missing(): Response
    {
        return $this->notFound();
    }

    private function notFound(): Response
    {
        $fallback = self::publicDir() . '/404.html';

        if (!is_file($fallback)) {
            return Response::error('PAGE_NOT_FOUND', 'Halaman tidak ditemukan', 404);
        }

        return Response::html($this->prepare((string) file_get_contents($fallback)), 404);
    }

    /**
     * @param array<string, string> $params
     */
    private static function urlPath(array $params): string
    {
        $page = trim((string) ($params['page'] ?? ''), '/');
        $rest = trim((string) ($params['rest'] ?? ''), '/');

        if ($page === '' && $rest === '') {
            return 'index';
        }

        if ($page === '') {
            return $rest;
        }

        return $rest === '' ? $page : $page . '/' . $rest;
    }

    private function prepare(string $html, string $directory = ''): string
    {
        $pageDir = self::publicDir() . ($directory === '' ? '' : '/' . $directory);
        $html = $this->applyTitle($this->withBase(
            Partials::render($this->applyGoogle($html), $pageDir, 0, self::publicDir()),
            $directory,
        ));

        $html = str_replace('__APP_ORIGIN__', htmlspecialchars(Request::appOrigin(), ENT_QUOTES), $html);

        return OpenGraph::ensure($html);
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

    private function withBase(string $html, string $directory = ''): string
    {
        $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
        $base = ($base === '' ? '' : $base) . '/';

        if ($directory !== '') {
            $base .= trim($directory, '/') . '/';
        }

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