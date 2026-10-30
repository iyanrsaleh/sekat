<?php

declare(strict_types=1);

$env = static function (string $key, ?string $default = null): ?string {
    $value = getenv($key);

    return $value === false || $value === '' ? $default : $value;
};

/**
 * Akun SMTP. Kosongkan host bila email cukup masuk ke storage/mail.
 * secure: tls (port 587), ssl (port 465), atau kosong.
 */
return [
    'host' => $env('MAIL_HOST', 'smtp.gmail.com'),
    'port' => (int) $env('MAIL_PORT', '587'),
    'user' => $env('MAIL_USER', 'ian.obet@gmail.com'),
    'pass' => $env('MAIL_PASS'),
    'from' => $env('MAIL_FROM', 'ian.obet@gmail.com'),
    'from_name' => $env('MAIL_FROM_NAME', 'Sekat'),
    'secure' => $env('MAIL_SECURE', 'tls'),
];
