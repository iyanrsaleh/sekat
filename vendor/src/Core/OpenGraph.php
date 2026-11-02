<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tag berbagi untuk jejaring sosial.
 * Halaman yang belum punya og:title mendapatkannya dari judul dan deskripsi.
 */
final class OpenGraph
{
    /**
     * @param array<string, string> $values
     */
    public static function block(array $values): string
    {
        $title = trim($values['title'] ?? '');
        $description = trim($values['description'] ?? '');
        $url = trim($values['url'] ?? '');
        $type = trim($values['type'] ?? 'website');
        $image = trim($values['image'] ?? '');
        $site = trim($values['site_name'] ?? '') ?: Settings::siteTitle();
        $locale = trim($values['locale'] ?? '') ?: 'id_ID';

        if ($description === '') {
            $description = $title;
        }

        $html = '';

        if ($url !== '') {
            $html .= '<link rel="canonical" href="' . self::e($url) . '">' . "\n";
        }

        $html .= self::meta('property', 'og:type', $type)
            . self::meta('property', 'og:locale', $locale)
            . self::meta('property', 'og:site_name', $site)
            . self::meta('property', 'og:title', $title)
            . self::meta('property', 'og:description', $description);

        if ($url !== '') {
            $html .= self::meta('property', 'og:url', $url);
        }

        $html .= self::meta('name', 'twitter:card', $image === '' ? 'summary' : 'summary_large_image')
            . self::meta('name', 'twitter:title', $title)
            . self::meta('name', 'twitter:description', $description);

        if ($image !== '') {
            $html .= self::meta('property', 'og:image', $image)
                . self::meta('property', 'og:image:alt', $title)
                . self::meta('name', 'twitter:image', $image);
        }

        foreach (['section' => 'article:section', 'published' => 'article:published_time', 'modified' => 'article:modified_time', 'author' => 'article:author'] as $key => $property) {
            $value = trim($values[$key] ?? '');

            if ($value !== '') {
                $html .= self::meta('property', $property, $value);
            }
        }

        return $html;
    }

    public static function ensure(string $html): string
    {
        if (stripos($html, 'property="og:title"') !== false || !preg_match('/<\/head>/i', $html)) {
            return $html;
        }

        $title = '';

        if (preg_match('/<title>(.*?)<\/title>/is', $html, $match) === 1) {
            $title = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8'));
        }

        $description = '';

        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $match) === 1
            || preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\']/i', $html, $match) === 1) {
            $description = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
        }

        if ($description === '' && preg_match('/<p[^>]*>(.*?)<\/p>/is', $html, $match) === 1) {
            $description = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8')));
        }

        if (mb_strlen($description) > 180) {
            $description = rtrim(mb_substr($description, 0, 177)) . '…';
        }

        $block = self::block([
            'title' => $title,
            'description' => $description,
            'url' => Request::pageUrl(),
            'type' => 'website',
        ]);

        return (string) preg_replace('/<\/head>/i', $block . '</head>', $html, 1);
    }

    private static function meta(string $attribute, string $name, string $content): string
    {
        return '<meta ' . $attribute . '="' . self::e($name) . '" content="' . self::e($content) . '">' . "\n";
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
