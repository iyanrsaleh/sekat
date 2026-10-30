<?php

declare(strict_types=1);

/**
 * Daftar Model yang dikelola mesin.
 *
 * - Semua Model di sini membuat tabel saat `php vendor/bin/migrate.php`.
 * - Hanya Model dengan $exposed = true yang mendapat route CRUD otomatis.
 */
return [
    App\Model\Post::class,
    App\Model\User::class,
    App\Model\AuthToken::class,
    App\Model\AuditLog::class,
    App\Model\EmailToken::class,
    App\Model\ApiToken::class,
    App\Model\ApiGrant::class,
    App\Model\Setting::class,
];