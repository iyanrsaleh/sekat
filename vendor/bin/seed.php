<?php

declare(strict_types=1);

/**
 * Mengisi data contoh lewat mesin Model.
 *
 *   php vendor/bin/seed.php
 */

$root = require dirname(__DIR__) . '/bootstrap.php';

$database = App\Core\Database::fromConfigFile($root . '/vendor/config/database.php');

$posts = new App\Repository\CrudRepository($database, App\Model\Post::class);

if ($posts->all() === []) {
    $posts->create([
        'title' => 'Post pertama',
        'body' => 'Isi post pertama dari seed.',
        'status' => 'published',
    ]);
    $posts->create([
        'title' => 'Post kedua',
        'body' => 'Isi post kedua dari seed.',
        'status' => 'published',
    ]);
    echo "Seed post: 2 data.\n";
} else {
    echo "Post sudah ada, dilewati.\n";
}

$users = new App\Repository\UserRepository($database);

if ($users->count() === 0) {
    $production = getenv('APP_ENV') === 'production';
    $password = $production ? (string) getenv('SEED_ADMIN_PASSWORD') : 'admin123';

    if ($production && strlen($password) < 12) {
        fwrite(STDERR, "Produksi menolak sandi admin bawaan. Isi SEED_ADMIN_PASSWORD minimal 12 karakter.\n");
        exit(1);
    }

    $users->create('Admin', 'admin@example.com', $password, App\Core\Role::ADMIN);
    echo $production
        ? "Seed user: admin@example.com (role admin, sandi dari SEED_ADMIN_PASSWORD)\n"
        : "Seed user: admin@example.com / admin123 (role admin)\n";
} else {
    echo "User sudah ada, dilewati.\n";
}
