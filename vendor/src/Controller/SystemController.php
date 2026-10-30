<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class SystemController
{
    public function __construct(private readonly Database $database)
    {
    }

    public function ping(): Response
    {
        return Response::json([
            'pong' => true,
            'time' => date(DATE_ATOM),
        ]);
    }

    public function notFound(): Response
    {
        return Response::error('NOT_FOUND', 'Endpoint tidak ditemukan', 404);
    }

    /**
     * Statistik ringkas (admin).
     *
     * @param array<string, string> $params
     */
    public function stats(array $params, Request $request): Response
    {
        $posts = (int) ($this->database->first(
            'SELECT COUNT(*) AS total FROM posts WHERE deleted_at IS NULL'
        )['total'] ?? 0);

        $users = (int) ($this->database->first('SELECT COUNT(*) AS total FROM users')['total'] ?? 0);

        $dayStart = date('Y-m-d 00:00:00');
        $auditToday = (int) ($this->database->first(
            'SELECT COUNT(*) AS total FROM audit_logs WHERE created_at >= :day',
            ['day' => $dayStart],
        )['total'] ?? 0);

        $uploads = glob(dirname(__DIR__, 3) . '/vendor/storage/uploads/*') ?: [];
        $uploadSize = 0;

        foreach ($uploads as $file) {
            if (is_file($file)) {
                $uploadSize += (int) filesize($file);
            }
        }

        $recentPosts = $this->database->select(
            'SELECT id, title, created_at FROM posts WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 5'
        );

        $recentAudit = $this->database->select(
            'SELECT a.action, a.user_id, a.ip, a.created_at, u.email
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT 5'
        );

        return Response::json([
            'counts' => [
                'posts' => $posts,
                'users' => $users,
                'audit_today' => $auditToday,
                'uploads' => count($uploads),
                'upload_bytes' => $uploadSize,
            ],
            'recent_posts' => $recentPosts,
            'recent_audit' => $recentAudit,
        ]);
    }
}