<?php

declare(strict_types=1);

return [
    'google' => [
        // Isi lewat environment GOOGLE_CLIENT_ID.
        'client_id' => \App\Core\Env::get('GOOGLE_CLIENT_ID') ?: '',
    ],
];
