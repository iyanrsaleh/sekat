<?php

declare(strict_types=1);

/**
 *   php vendor/bin/purge.php
 *
 * - Token login / email yang sudah lewat masa berlaku atau sudah dipakai.
 * - File log lebih lama dari 14 hari.
 * - File rate limit yang sudah kedaluwarsa.
 */

$root = require dirname(__DIR__) . '/bootstrap.php';

$database = App\Core\Database::fromConfigFile($root . '/vendor/config/database.php');
$now = date('Y-m-d H:i:s');

$deleted = $database->execute('DELETE FROM auth_tokens WHERE expires_at <= :now', ['now' => $now]);
echo "Token kedaluwarsa dihapus: {$deleted}\n";

$emailDeleted = $database->execute(
    'DELETE FROM email_tokens WHERE expires_at <= :now OR used_at IS NOT NULL',
    ['now' => $now],
);
echo "Token email dibersihkan: {$emailDeleted}\n";

$logDirectory = $root . '/vendor/storage/logs';
$limit = time() - (14 * 24 * 60 * 60);
$removed = 0;

foreach (glob($logDirectory . '/app-*.log') ?: [] as $log) {
    if (is_file($log) && filemtime($log) !== false && filemtime($log) < $limit) {
        unlink($log);
        $removed++;
    }
}

echo "Log lama dihapus: {$removed}\n";

$cacheRemoved = 0;

foreach (glob($root . '/vendor/storage/cache/*/*.json') ?: [] as $cache) {
    if (is_file($cache) && filemtime($cache) !== false && filemtime($cache) < time() - 3600) {
        unlink($cache);
        $cacheRemoved++;
    }
}

echo "Cache kedaluwarsa dihapus: {$cacheRemoved}\n";

$mailRemoved = 0;
$mailLimit = time() - (30 * 24 * 60 * 60);

foreach (glob($root . '/vendor/storage/mail/*.eml') ?: [] as $mail) {
    if (is_file($mail) && filemtime($mail) !== false && filemtime($mail) < $mailLimit) {
        unlink($mail);
        $mailRemoved++;
    }
}

echo "Email outbox lama dihapus: {$mailRemoved}\n";