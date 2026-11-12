<?php

declare(strict_types=1);

/**
 * Bootstrap bersama untuk web dan CLI.
 * Mengembalikan root project (folder framework/).
 */

$root = dirname(__DIR__);

// .env dibaca per request lewat App\Core\Env::get(), tanpa putenv() (lihat Env.php:
// putenv bocor antar-aplikasi di Apache mod_php thread-safe).
require_once $root . '/vendor/src/Core/Env.php';
App\Core\Env::load($root . '/.env');

$autoload = $root . '/deps/autoload.php';

if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'App\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $file = $root . '/vendor/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($file)) {
            require $file;
        }
    });
}

define('FRAMEWORK_ROOT', $root);

App\Core\Cache::setDirectory($root . '/vendor/storage/cache');
App\Core\Mailer::setDirectory($root . '/vendor/storage/mail');
App\Core\Queue::setDirectory($root . '/vendor/storage/queue');

return $root;