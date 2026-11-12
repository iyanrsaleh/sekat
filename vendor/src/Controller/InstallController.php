<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Install\EnvironmentWriter;
use App\Install\Installer;

final class InstallController
{
    public function __construct(
        private readonly Installer $installer,
        private readonly EnvironmentWriter $env,
    ) {
    }

    public function status(array $params, Request $request): Response
    {
        return Response::json($this->installer->status());
    }

    public function check(array $params, Request $request): Response
    {
        return Response::json($this->installer->check());
    }

    public function config(array $params, Request $request): Response
    {
        $body = $request->json();

        $db = [
            'host' => (string) ($body['host'] ?? ''),
            'port' => $body['port'] ?? '',
            'database' => (string) ($body['database'] ?? ''),
            'username' => (string) ($body['username'] ?? ''),
            'password' => (string) ($body['password'] ?? ''),
        ];

        $this->installer->config($db);

        return Response::json(['ok' => true, 'message' => 'Konfigurasi database disimpan']);
    }

    public function migrate(array $params, Request $request): Response
    {
        $result = $this->installer->migrate();

        return Response::json([
            'ok' => true,
            'message' => 'Migrasi dan seed selesai',
            'admin' => [
                'email' => $result['admin_email'],
                'created' => $result['admin_created'],
            ],
        ]);
    }

    public function seed(array $params, Request $request): Response
    {
        if (!$this->env->hasDatabaseCredentials()) {
            throw new HttpException(422, 'ENV_NOT_CONFIGURED', 'Konfigurasi database belum diisi');
        }

        $root = dirname(__DIR__, 3);
        $database = Database::fromConfigFile($root . '/vendor/config/database.php');
        $users = new \App\Repository\UserRepository($database);

        if ($users->count() > 0) {
            return Response::json(['ok' => true, 'message' => 'User sudah ada, dilewati']);
        }

        $password = (string) (\App\Core\Env::get('SEED_ADMIN_PASSWORD') ?: 'admin123');
        $users->create('Admin', 'admin@example.com', $password, \App\Core\Role::ADMIN);

        return Response::json([
            'ok' => true,
            'message' => 'Seed admin selesai',
            'admin' => ['email' => 'admin@example.com'],
        ]);
    }
}
