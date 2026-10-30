<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Partials HTML ala smart routing: elemen dengan atribut `partial="PATH"`
 * diganti isi file partial saat halaman dikirim server.
 *
 * Contoh:
 *   <header partial="./header.html"></header>
 *
 * PATH relatif terhadap folder halaman (public/ atau public/dashboard/).
 * Path dicegah keluar dari folder web (tidak boleh `..` menembus).
 */
final class Partials
{
    private const MAX_DEPTH = 5;

    public static function render(string $html, string $baseDir, int $depth = 0): string
    {
        if ($depth >= self::MAX_DEPTH) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/<([a-z][a-z0-9-]*)[^>]*\bpartial="([^"]+)"[^>]*>.*?<\/\1>/is',
            static function (array $matches) use ($baseDir, $depth): string {
                $content = self::load($baseDir, $matches[2]);

                if ($content === null) {
                    return $matches[0];
                }

                return self::render($content, $baseDir, $depth + 1);
            },
            $html,
        );
    }

    private static function load(string $baseDir, string $path): ?string
    {
        $root = realpath($baseDir);

        if ($root === false) {
            return null;
        }

        $resolved = realpath($root . '/' . ltrim($path, '/\\'));

        if ($resolved === false || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        if (!is_file($resolved)) {
            return null;
        }

        $content = file_get_contents($resolved);

        return $content === false ? null : $content;
    }
}