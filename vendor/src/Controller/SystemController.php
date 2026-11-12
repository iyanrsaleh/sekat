<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Database;
use App\Core\Queue;
use App\Core\Request;
use App\Core\Response;

final class SystemController
{
    public function __construct(private readonly Database $database)
    {
    }

    public function ping(array $params, Request $request): Response
    {
        return Response::json([
            'pong' => true,
            'time' => date(DATE_ATOM),
        ]);
    }

    /**
     * Cek hidup untuk peantau uptime. Tanpa auth. HTTP 503 bila ada yang gagal.
     *
     * @param array<string, string> $params
     */
    public function health(array $params, Request $request): Response
    {
        $root = defined('FRAMEWORK_ROOT') ? (string) FRAMEWORK_ROOT : dirname(__DIR__, 3);
        $app = require $root . '/vendor/config/app.php';
        $cacheDir = $root . '/vendor/storage/cache';
        $queueDir = $root . '/vendor/storage/queue';

        $cacheWritable = self::writableDir($cacheDir);
        $queueWritable = self::writableDir($queueDir);

        $databaseOk = false;
        try {
            $this->database->first('SELECT 1 AS ok');
            $databaseOk = true;
        } catch (\Throwable) {
            $databaseOk = false;
        }

        $pending = 0;
        try {
            $pending = Queue::pendingCount();
        } catch (\Throwable) {
            $queueWritable = false;
        }

        $ok = $databaseOk && $cacheWritable && $queueWritable;

        return Response::json([
            'ok' => $ok,
            'version' => (string) ($app['version'] ?? ''),
            'time' => date(DATE_ATOM),
            'checks' => [
                'database' => $databaseOk,
                'cache_writable' => $cacheWritable,
                'queue_writable' => $queueWritable,
                'queue_pending' => $pending,
            ],
        ], $ok ? 200 : 503);
    }

    private static function writableDir(string $directory): bool
    {
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        $probe = $directory . '/.health-' . bin2hex(random_bytes(4));
        $written = @file_put_contents($probe, '1');
        if ($written === false) {
            return false;
        }
        @unlink($probe);

        return true;
    }

    public function notFound(array $params, Request $request): Response
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