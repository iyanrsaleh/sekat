<?php

declare(strict_types=1);

$environment = \App\Core\Env::get('APP_ENV') ?: 'local';
$isProduction = $environment === 'production';

return [
    'name' => 'Sekat',
    'version' => '1.3.0',
    'env' => $environment,
    'debug' => !$isProduction,
    'timezone' => 'Asia/Jakarta',
];