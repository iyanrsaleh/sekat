<?php

declare(strict_types=1);

namespace App\Controller;

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
        $public = getenv('FRAMEWORK_PUBLIC');

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
        return $this->page('index.html');
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params, Request $request): Response
    {
        $page = (string) preg_replace('/[^a-z0-9_-]/i', '', (string) ($params['page'] ?? 'index'));
        $page = $page === '' ? 'index' : $page;

        return $this->page($page . '.html');
    }

    private function page(string $file): Response
    {
        $path = self::dashboardDir() . '/' . $file;

        if (!is_file($path)) {
            $path = self::publicDir() . '/404.html';
        }

        return Response::html($this->prepare((string) file_get_contents($path)));
    }

    private function prepare(string $html): string
    {
        $html = $this->applyTitle($this->withBase(Partials::render($html, self::dashboardDir())));

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