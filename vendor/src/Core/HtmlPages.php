<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Memetakan path URL ke berkas HTML di bawah satu folder.
 *
 * public/<nama>.html menang atas public/<nama>/index.html.
 * public/<cabang>/index.html dilayani di /<cabang>.
 * public/<cabang>/<halaman>.html dilayani di /<cabang>/<halaman>.
 * public/<cabang>/<halaman>/index.html dilayani di /<cabang>/<halaman>.
 */
final class HtmlPages
{
    /**
     * @return array{file: string, directory: string}|null
     * directory adalah folder URL berkas, relatif terhadap $root, tanpa garis miring.
     */
    public static function locate(string $root, string $urlPath): ?array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $segments = self::segments($urlPath);

        if ($segments === null) {
            return null;
        }

        $relative = implode('/', $segments);
        $candidates = $relative === ''
            ? [['file' => $root . '/index.html', 'directory' => '']]
            : [
                ['file' => $root . '/' . $relative . '.html', 'directory' => self::parentDir($relative)],
                ['file' => $root . '/' . $relative . '/index.html', 'directory' => $relative],
            ];

        $rootReal = realpath($root);

        if ($rootReal === false) {
            return null;
        }

        $rootReal = rtrim(str_replace('\\', '/', $rootReal), '/');

        foreach ($candidates as $candidate) {
            if (!is_file($candidate['file'])) {
                continue;
            }

            $real = realpath($candidate['file']);

            if ($real === false) {
                continue;
            }

            $real = str_replace('\\', '/', $real);

            if (!str_starts_with($real, $rootReal . '/')) {
                continue;
            }

            return ['file' => $real, 'directory' => $candidate['directory']];
        }

        return null;
    }

    /**
     * @return array<int, string>|null
     */
    private static function segments(string $urlPath): ?array
    {
        $urlPath = trim(str_replace('\\', '/', $urlPath), '/');

        if ($urlPath === '') {
            return [];
        }

        $clean = [];

        foreach (explode('/', $urlPath) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }

            if (preg_match('/^[a-z0-9_-]+$/i', $segment) !== 1) {
                return null;
            }

            $clean[] = $segment;
        }

        return $clean;
    }

    private static function parentDir(string $relative): string
    {
        $slash = strrpos($relative, '/');

        return $slash === false ? '' : substr($relative, 0, $slash);
    }
}
