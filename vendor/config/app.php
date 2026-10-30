<?php

declare(strict_types=1);

$environment = getenv('APP_ENV') ?: 'local';
$isProduction = $environment === 'production';

return [
    'name' => 'Sekat',
    'version' => '1.0.0',
    'env' => $environment,
    'debug' => !$isProduction,
    'timezone' => 'Asia/Jakarta',
];