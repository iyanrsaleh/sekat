<?php

declare(strict_types=1);

/**
 * Memproses antrian file.
 *
 *   php vendor/bin/queue.php
 *   php vendor/bin/queue.php 20
 *
 * Argumen opsional: jumlah job maksimal per jalan (default 10).
 */

$root = require dirname(__DIR__) . '/bootstrap.php';

$limit = isset($argv[1]) ? max(1, (int) $argv[1]) : 10;
$done = 0;
$failed = 0;

for ($i = 0; $i < $limit; $i++) {
    $job = App\Core\Queue::claim();

    if ($job === null) {
        break;
    }

    try {
        match ($job['type']) {
            'mail.send' => (static function (array $payload): void {
                $to = trim((string) ($payload['to'] ?? ''));
                $subject = (string) ($payload['subject'] ?? '');
                $body = (string) ($payload['body'] ?? '');

                if ($to === '' || $subject === '') {
                    throw new RuntimeException('mail.send butuh to dan subject');
                }

                App\Core\Mailer::send($to, $subject, $body);
            })($job['payload']),
            default => throw new RuntimeException('Tipe job tidak dikenal: ' . $job['type']),
        };

        App\Core\Queue::done($job);
        $done++;
        echo "ok {$job['type']} {$job['id']}\n";
    } catch (Throwable $error) {
        App\Core\Queue::fail($job, $error->getMessage());
        $failed++;
        echo "fail {$job['type']} {$job['id']}: {$error->getMessage()}\n";
    }
}

echo "selesai: {$done} ok, {$failed} gagal, sisa " . App\Core\Queue::pendingCount() . "\n";
