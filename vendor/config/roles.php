<?php

declare(strict_types=1);

/**
 * Role yang dikenal framework.
 *
 * Halaman daftar hanya membuat "user".
 * "admin" dibuat lewat seed, bukan dari body pendaftaran.
 * Nama lain ditambah di daftar ini. Role di luar daftar ditolak saat akun dibuat.
 * Nama lain tidak diperlakukan sebagai admin.
 */

return [
    'user',
    'admin',
];
