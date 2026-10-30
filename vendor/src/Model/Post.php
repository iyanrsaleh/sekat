<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Model;

/**
 * Cukup definisikan tabel dan field di sini.
 * Tabel, repository, route CRUD, dan validasi dibuat otomatis oleh mesin.
 */
final class Post extends Model
{
    protected static string $table = 'posts';

    protected static bool $softDelete = true;

    protected static array $fields = [
         'title' => [
         'type' => 'string',
         'max' => 200,
         'required' => true,
         'label' => 'Judul',
         'rules' => ['min:5']
         ],
        'body' => [
         'type' => 'text',
         'required' => true,
         'label' => 'Isi'
         ],
        'user_id' => [
         'type' => 'int',
         'unsigned' => true,
         'required' => false,
         'label' => 'Penulis'
         ],
    ];

    protected static array $relations = [
        'user' => ['type' => 'belongsTo', 'model' => User::class, 'foreignKey' => 'user_id'],
    ];
}