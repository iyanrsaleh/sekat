<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Model\Post;

/**
 * Contoh query kustom di luar CRUD otomatis.
 * SQL ditulis eksplisit, parameter selalu di-bind (tidak pernah disambung string).
 */
final class PostSearchController
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(array $params, Request $request): Response
    {
        $keyword = trim((string) $request->query('q', ''));

        if ($keyword === '') {
            return Response::json(['items' => [], 'q' => '']);
        }

        $rows = $this->database->select(
            'SELECT * FROM posts
             WHERE title LIKE :title OR body LIKE :body
             ORDER BY id DESC
             LIMIT 50',
            ['title' => '%' . $keyword . '%', 'body' => '%' . $keyword . '%'],
        );

        return Response::json([
            'q' => $keyword,
            'items' => array_map(static fn (array $row): array => Post::serialize($row), $rows),
        ]);
    }
}