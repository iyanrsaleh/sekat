<?php

declare(strict_types=1);

/**
 * Bootstrap bersama untuk web dan CLI.
 * Mengembalikan root project (folder framework/).
 */

$root = dirname(__DIR__);

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

return $root;