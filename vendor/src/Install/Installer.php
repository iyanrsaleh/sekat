<?php

declare(strict_types=1);

namespace App\Install;

use App\Core\Database;
use App\Core\Schema;
use App\Model\User;
use App\Repository\UserRepository;

final class Installer
{
    public function __construct(
        private readonly EnvironmentWriter $env,
        private readonly string $root,
    ) {
    }

    public function status(): array
    {
        if (!$this->env->hasDatabaseCredentials()) {
            return ['installed' => false, 'reason' => 'env_missing'];
        }

        try {
            $database = Database::fromConfigFile($this->root . '/vendor/config/database.php');
            $database->first('SELECT 1 AS ok');

            $tables = $database->select(
                'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()',
            );
            $names = array_map(static fn (array $row): string => (string) $row['TABLE_NAME'], $tables);

            if (!in_array('users', $names, true)) {
                return ['installed' => false, 'reason' => 'tables_missing'];
            }

            return ['installed' => true];
        } catch (\Throwable) {
            return ['installed' => false, 'reason' => 'db_unreachable'];
        }
    }

    public function check(): array
    {
        $extensions = [
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'mbstring' => extension_loaded('mbstring'),
            'json' => extension_loaded('json'),
        ];

        $writable = [
            'root' => $this->writable($this->root),
            'env' => $this->writable(dirname($this->root . '/.env')),
            'vendor_storage' => $this->writable($this->root . '/vendor/storage'),
        ];

        $phpOk = PHP_VERSION_ID >= 80100;

        $ok = $phpOk
            && !in_array(false, $extensions, true)
            && !in_array(false, $writable, true);

        return [
            'php' => PHP_VERSION,
            'php_ok' => $phpOk,
            'extensions' => $extensions,
            'writable' => $writable,
            'ok' => $ok,
        ];
    }

    public function config(array $db): void
    {
        $this->validateDbInput($db);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4',
            $db['host'],
            (int) $db['port'],
        );

        try {
            $pdo = new \PDO($dsn, $db['username'], $db['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('SELECT 1');
        } catch (\Throwable $e) {
            throw new \App\Core\HttpException(422, 'DB_CONNECT_FAILED', 'Tidak dapat terhubung ke database: ' . $e->getMessage());
        }

        $this->env->write([
            'host' => $db['host'],
            'port' => (int) $db['port'],
            'database' => $db['database'],
            'username' => $db['username'],
            'password' => $db['password'],
        ]);
    }

    public function migrate(): array
    {
        if (!$this->env->hasDatabaseCredentials()) {
            throw new \App\Core\HttpException(422, 'ENV_NOT_CONFIGURED', 'Konfigurasi database belum diisi');
        }

        $root = $this->root;
        $configFile = $root . '/vendor/config/database.php';
        $database = Database::fromConfigFile($configFile);

        $config = require $configFile;

        // Buat database bila belum ada
        $serverDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']);
        $serverPdo = new \PDO($serverDsn, $config['username'], $config['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $serverPdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $config['database'],
        ));
        $serverPdo->exec(sprintf('USE `%s`', $config['database']));

        $models = require $root . '/vendor/config/models.php';

        $results = [];
        foreach ($models as $model) {
            $result = Schema::sync($database, $model);
            $results[$model::table()] = $result;

            foreach (Schema::syncForeignKeys($database, $model) as $constraint) {
                $results[$model::table()]['foreign_keys'][] = $constraint;
            }
        }

        // Seed admin
        $users = new UserRepository($database);
        $adminCreated = false;
        if ($users->count() === 0) {
            $password = (string) (\App\Core\Env::get('SEED_ADMIN_PASSWORD') ?: 'admin123');
            $users->create('Admin', 'admin@example.com', $password, \App\Core\Role::ADMIN);
            $adminCreated = true;
        }

        return [
            'tables' => $results,
            'admin_created' => $adminCreated,
            'admin_email' => 'admin@example.com',
        ];
    }

    private function validateDbInput(array $db): void
    {
        if ($db['host'] === '' || $db['host'] === null) {
            throw new \App\Core\HttpException(422, 'VALIDATION_ERROR', 'Host database wajib diisi');
        }

        if (!is_numeric($db['port']) || $db['port'] === '') {
            throw new \App\Core\HttpException(422, 'VALIDATION_ERROR', 'Port database harus berupa angka');
        }

        if ($db['database'] === '' || $db['database'] === null) {
            throw new \App\Core\HttpException(422, 'VALIDATION_ERROR', 'Nama database wajib diisi');
        }

        if ($db['username'] === '' || $db['username'] === null) {
            throw new \App\Core\HttpException(422, 'VALIDATION_ERROR', 'Username database wajib diisi');
        }
    }

    private function writable(string $directory): bool
    {
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        $probe = $directory . '/.install-' . bin2hex(random_bytes(4));
        $written = @file_put_contents($probe, '1');
        if ($written === false) {
            return false;
        }
        @unlink($probe);

        return true;
    }
}
