<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\HtmlPages;
use App\Core\Partials;
use App\Core\Request;
use App\Core\Response;

/**
 * Area admin (dashboard), terpisah dari halaman publik.
 * Halaman dibaca dari public/dashboard/, akses data via API yang sudah
 * dilindungi (admin) seperti /api/stats dan /api/audit-logs.
 */
final class DashboardController
{
    private static function publicDir(): string
    {
        $public = \App\Core\Env::get('FRAMEWORK_PUBLIC');

        if (is_string($public) && $public !== '') {
            return rtrim(str_replace('\\', '/', $public), '/');
        }

        return str_replace('\\', '/', __DIR__ . '/../../../public');
    }

    private static function dashboardDir(): string
    {
        return self::publicDir() . '/dashboard';
    }

    /**
     * @param array<string, string> $params
     */
    public function index(array $params, Request $request): Response
    {
        return $this->renderPath('');
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params, Request $request): Response
    {
        $page = trim((string) ($params['page'] ?? ''), '/');
        $rest = trim((string) ($params['rest'] ?? ''), '/');
        $path = $page === '' ? $rest : ($rest === '' ? $page : $page . '/' . $rest);

        return $this->renderPath($path);
    }

    private function renderPath(string $urlPath): Response
    {
        $found = HtmlPages::locate(self::dashboardDir(), $urlPath === '' ? 'index' : $urlPath);

        if ($found === null) {
            return $this->missing();
        }

        return Response::html($this->prepare((string) file_get_contents($found['file']), $found['directory']));
    }

    private function missing(): Response
    {
        $file = self::dashboardDir() . '/404.html';

        if (!is_file($file)) {
            return Response::error('PAGE_NOT_FOUND', 'Halaman tidak ditemukan', 404);
        }

        return Response::html($this->prepare((string) file_get_contents($file)), 404);
    }

    private function prepare(string $html, string $directory = ''): string
    {
        $pageDir = self::dashboardDir() . ($directory === '' ? '' : '/' . $directory);
        $html = $this->applyTitle($this->withBase(Partials::render($html, $pageDir, 0, self::dashboardDir())));

        return str_replace('__APP_ORIGIN__', htmlspecialchars(Request::appOrigin(), ENT_QUOTES), $html);
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
        // Base diarahkan ke folder dashboard, sehingga asset relatif
        // (assets/...) memuat dari public/dashboard/assets/ bukan public/assets/.
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $base = ($base === '' ? '' : $base) . '/dashboard/';

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