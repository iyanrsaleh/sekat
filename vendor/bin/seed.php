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
    $posts->create(['title' => 'Post pertama', 'body' => 'Isi post pertama dari seed.']);
    $posts->create(['title' => 'Post kedua', 'body' => 'Isi post kedua dari seed.']);
    echo "Seed post: 2 data.\n";
} else {
    echo "Post sudah ada, dilewati.\n";
}

$users = new App\Repository\UserRepository($database);

if ($users->count() === 0) {
    $users->create('Admin', 'admin@example.com', 'admin123', App\Core\Role::ADMIN);
    echo "Seed user: admin@example.com / admin123 (role admin)\n";
} else {
    echo "User sudah ada, dilewati.\n";
}