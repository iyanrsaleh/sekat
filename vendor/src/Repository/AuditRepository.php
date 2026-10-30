<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class AuditRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function record(string $action, ?int $userId = null, array $meta = []): void
    {
        $this->database->execute(
            'INSERT INTO audit_logs (action, user_id, ip, meta) VALUES (:action, :user_id, :ip, :meta)',
            [
                'action' => $action,
                'user_id' => $userId,
                'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'),
                'meta' => $meta === [] ? null : (string) json_encode($meta, JSON_UNESCAPED_UNICODE),
            ],
        );
    }
}