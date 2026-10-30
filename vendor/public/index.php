<?php

declare(strict_types=1);

use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;

$root = require dirname(__DIR__) . '/bootstrap.php';

$config = require $root . '/vendor/config/app.php';

date_default_timezone_set($config['timezone'] ?? 'UTC');

ErrorHandler::register((bool) ($config['debug'] ?? false), $root . '/vendor/storage/logs');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Path tanpa base (mirip Request::capture) untuk memilih kebijakan CSP.
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$requestPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?: '/');
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

if ($scriptName !== '' && str_starts_with($requestPath, $scriptName)) {
    $requestPath = substr($requestPath, strlen($scriptName));
} else {
    $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($base !== '' && $base !== '.' && str_starts_with($requestPath, $base)) {
        $requestPath = substr($requestPath, strlen($base));
    }
}

$requestPath = '/' . trim($requestPath, '/');

if ($requestPath === '/signin' || $requestPath === '/signup') {
    // Google menolak tombol masuk bila origin tidak ikut terkirim.
    // same-origin menghapus Referer ke accounts.google.com.
    header('Referrer-Policy: no-referrer-when-downgrade');
    header(
        "Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; "
        . "style-src 'self' 'unsafe-inline' https://accounts.google.com; script-src 'self' https://accounts.google.com; "
        . "frame-src https://accounts.google.com; connect-src 'self' https://accounts.google.com; "
        . "base-uri 'self'; form-action 'self'; frame-ancestors 'none'"
    );
} elseif (str_starts_with($requestPath, '/dashboard')) {
    // Dashboard admin memakai template (Dashforge) yang butuh font/imgs eksternal
    // dan inline style/script. Kebijakan ini hanya untuk area dashboard.
    header(
        "Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; "
        . "style-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline'; "
        . "font-src 'self' https: data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'"
    );
} else {
    header(
        "Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; "
        . "script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'"
    );
}

$router = new Router();

/** @var callable(Router): void $routes */
$routes = require $root . '/vendor/config/routes.php';
$routes($router);

$router->dispatch(Request::capture())->send();