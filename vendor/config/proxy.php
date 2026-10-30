<?php

declare(strict_types=1);

/**
 * Daftar alias proxy yang boleh dipanggil lewat /api/{alias}/....
 * URL tujuan disimpan terpisah di config/upstreams.php (tidak diekspos).
 */
return [
    'aliases' => ['feed', 'self', 'jsonplaceholder'],
];