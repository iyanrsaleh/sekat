<?php

declare(strict_types=1);

/**
 * Cek status instalasi Sekat.
 *
 *   php vendor/bin/install-status.php
 */

$root = require dirname(__DIR__) . '/bootstrap.php';

$env = new \App\Install\EnvironmentWriter($root);
$installer = new \App\Install\Installer($env, $root);

$status = $installer->status();

if ($status['installed'] === true) {
    echo "Sudah terinstal.\n";
    exit(0);
}

echo "Belum terinstal. Alasan: " . $status['reason'] . "\n";
exit(1);
