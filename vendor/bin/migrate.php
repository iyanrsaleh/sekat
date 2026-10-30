<?php

declare(strict_types=1);

/**
 * Menjalankan migration MySQL.
 *
 *   php vendor/bin/migrate.php
 *
 * 1. Membuat database bila belum ada.
 * 2. Menjalankan file SQL di vendor/database/migrations satu kali.
 * 3. Menyinkronkan tabel dari setiap Model (buat tabel / tambah kolom baru).
 */

$root = require dirname(__DIR__) . '/bootstrap.php';

$config = require $root . '/vendor/config/database.php';
$directory = $root . '/vendor/database/migrations';

$serverDsn = sprintf('mysql:host=%s;port=%d;charset=%s', $config['host'], $config['port'], $config['charset']);
$pdo = new PDO($serverDsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec(sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    $config['database'],
));

echo "Database siap: {$config['database']}\n";

$pdo->exec(sprintf('USE `%s`', $config['database']));

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        filename VARCHAR(191) NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_filename (filename)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4'
);

$applied = $pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob($directory . '/*.sql') ?: [];
sort($files);

$ran = 0;

foreach ($files as $file) {
    $name = basename($file);

    if (in_array($name, $applied, true)) {
        continue;
    }

    $pdo->exec((string) file_get_contents($file));

    $statement = $pdo->prepare('INSERT INTO migrations (filename) VALUES (:filename)');
    $statement->execute(['filename' => $name]);

    $ran++;
    echo "migration: {$name}\n";
}

echo $ran === 0 ? "Tidak ada migration baru.\n" : "Selesai: {$ran} migration.\n";

$database = App\Core\Database::fromConfigFile($root . '/vendor/config/database.php');

/** @var array<int, class-string<App\Core\Model>> $models */
$models = require $root . '/vendor/config/models.php';

foreach ($models as $model) {
    $result = App\Core\Schema::sync($database, $model);

    $status = $result['created'] ? 'dibuat' : 'diperiksa';
    $extra = [];

    if ($result['columns'] !== []) {
        $extra[] = 'kolom baru: ' . implode(', ', $result['columns']);
    }

    if ($result['indexes'] !== []) {
        $extra[] = 'unique baru: ' . implode(', ', $result['indexes']);
    }

    echo sprintf(
        "tabel %s (%s)%s\n",
        $model::table(),
        $status,
        $extra === [] ? '' : ' - ' . implode('; ', $extra),
    );
}

foreach ($models as $model) {
    foreach (App\Core\Schema::syncForeignKeys($database, $model) as $constraint) {
        echo "relasi: {$constraint}\n";
    }
}