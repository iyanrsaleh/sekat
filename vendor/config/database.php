<?php

declare(strict_types=1);

$env = static function (string $key, ?string $default = null): ?string {
    $value = getenv($key);

    return $value === false || $value === '' ? $default : $value;
};

return [
    'host' => $env('DB_HOST', '127.0.0.1'),
    'port' => (int) $env('DB_PORT', '3310'),
    'database' => $env('DB_NAME', 'framework'),
    'username' => $env('DB_USER', 'root'),
    'password' => $env('DB_PASS', ''),
    'charset' => 'utf8mb4',
];
