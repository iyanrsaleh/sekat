<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Database;
use App\Core\Model;
use App\Core\OpenGraph;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Repository\CrudRepository;

/**
 * Satu segmen URL.
 * Berkas public/<nama>.html dan folder public/<nama>/index.html tetap dilayani.
 * Segmen yang cocok dengan slug Model menampilkan halaman tautan, tanpa id.
 */
final class PermalinkController
{
    /**
     * @param array<int, class-string<Model>> $models
     */
    public function __construct(
        private readonly Database $database,
        private readonly array $models,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params, Request $request): Response
    {
        $pages = new PageController();
        $page = (string) preg_replace('/[^a-z0-9_-]/i', '', (string) ($params['page'] ?? ''));
        $this->fillSlugs();

        if ($page !== '' && $pages->has($page)) {
            return $pages->show(['page' => $page], $request);
        }

        $found = $this->find(strtolower($page));

        if ($found === null) {
            return $pages->missing();
        }

        $template = dirname(__DIR__, 3) . '/vendor/templates/permalink.html';

        if (!is_file($template)) {
            return $pages->missing();
        }

        $html = str_replace(
            array_keys($found['replace']),
            array_values($found['replace']),
            (string) file_get_contents($template),
        );

        return $pages->html($html);
    }

    private function fillSlugs(): void
    {
        foreach ($this->models as $model) {
            if (!$model::exposed() || !isset($model::fields()['slug'])) {
                continue;
            }

            (new CrudRepository($this->database, $model))->ensureSlugs();
        }
    }

    /**
     * @return array{replace: array<string, string>}|null
     */
    private function find(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        foreach ($this->models as $model) {
            if (!$model::exposed() || !isset($model::fields()['slug']) || !isset($model::fields()['title'])) {
                continue;
            }

            $sql = sprintf('SELECT * FROM `%s` WHERE slug = :slug', $model::table());

            if ($model::softDeletes()) {
                $sql .= ' AND deleted_at IS NULL';
            }

            $row = $this->database->first($sql . ' LIMIT 1', ['slug' => $slug]);

            if ($row === null) {
                continue;
            }

            return ['replace' => $this->markup($model, $row)];
        }

        return null;
    }

    /**
     * @param class-string<Model> $model
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function markup(string $model, array $row): array
    {
        $title = trim((string) ($row['title'] ?? ''));
        $description = $this->excerpt($this->text($model, $row, ['body', 'content', 'description', 'excerpt']));
        $image = $this->absolute($this->text($model, $row, ['image', 'cover', 'thumbnail', 'picture']));
        $section = $this->text($model, $row, ['category', 'section']);
        $author = $this->author($row);
        $url = Request::appOrigin() . '/' . (string) ($row['slug'] ?? '');
        $published = $this->iso((string) ($row['created_at'] ?? ''));
        $modified = $this->iso((string) ($row['updated_at'] ?? $row['created_at'] ?? ''));

        $values = [
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'type' => 'article',
            'image' => $image,
            'site_name' => Settings::siteTitle(),
            'section' => $section,
            'published' => $published,
            'modified' => $modified,
            'author' => $author,
        ];

        $linked = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $title,
            'description' => $description === '' ? $title : $description,
            'datePublished' => $published,
            'dateModified' => $modified,
            'inLanguage' => 'id-ID',
            'mainEntityOfPage' => $url,
            'publisher' => ['@type' => 'Organization', 'name' => Settings::siteTitle()],
        ];

        if ($section !== '') {
            $linked['articleSection'] = $section;
        }

        if ($author !== '') {
            $linked['author'] = ['@type' => 'Person', 'name' => $author];
        }

        if ($image !== '') {
            $linked['image'] = [$image];
        }

        $json = (string) json_encode(
            $linked,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        return [
            '__TITLE__' => $this->e($title),
            '__DESCRIPTION__' => $this->e($description === '' ? $title : $description),
            '__LIST__' => $this->e($model::resource()),
            '__META__' => OpenGraph::block($values) . '<script type="application/ld+json">' . $json . '</script>',
        ];
    }

    /**
     * @param class-string<Model> $model
     * @param array<string, mixed> $row
     * @param array<int, string> $names
     */
    private function text(string $model, array $row, array $names): string
    {
        $fields = $model::fields();

        foreach ($names as $name) {
            if (!isset($fields[$name])) {
                continue;
            }

            return trim((string) ($row[$name] ?? ''));
        }

        return '';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function author(array $row): string
    {
        $userId = (int) ($row['user_id'] ?? 0);

        if ($userId <= 0) {
            return '';
        }

        $user = $this->database->first('SELECT name FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);

        return trim((string) ($user['name'] ?? ''));
    }

    private function excerpt(string $body): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($body)));

        if (mb_strlen($text) <= 180) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, 177)) . '…';
    }

    private function absolute(string $image): string
    {
        if ($image === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $image) === 1) {
            return $image;
        }

        return Request::appOrigin() . '/' . ltrim($image, '/');
    }

    private function iso(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $time = strtotime($value);

        return $time === false ? '' : date('c', $time);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

}
