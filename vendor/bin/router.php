<?php

declare(strict_types=1);

/**
 * Router untuk PHP built-in server (development / CI).
 *
 *   php -S 127.0.0.1:8123 -t . vendor/bin/router.php
 */

$path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');

if (str_starts_with($path, '/assets/')) {
    $asset = $documentRoot . '/public' . $path;

    if (is_file($asset)) {
        $mime = match (strtolower(pathinfo($asset, PATHINFO_EXTENSION))) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };

        header('Content-Type: ' . $mime);
        readfile($asset);

        return true;
    }
}

if ($path !== '/' && is_file($documentRoot . $path)) {
    return false;
}

require $documentRoot . '/index.php';

return true;