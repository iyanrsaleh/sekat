<?php

declare(strict_types=1);

return [
    'google' => [
        // Isi lewat environment GOOGLE_CLIENT_ID.
        'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
    ],
];
