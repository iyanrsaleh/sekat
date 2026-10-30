# Model

Folder ini berisi definisi data. Satu file = satu Model = satu resource API.

Menulis Model adalah satu-satunya pekerjaan yang diperlukan untuk menambah fitur CRUD. Tabel database, repository, route, dan validasi dibuat otomatis oleh mesin.

## Membuat Model

Contoh `Post.php`:

```php
<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Model;

final class Post extends Model
{
    protected static string $table = 'posts';

    protected static array $fields = [
        'title' => ['type' => 'string', 'max' => 200, 'required' => true, 'label' => 'Judul'],
        'body'  => ['type' => 'text', 'required' => true, 'label' => 'Isi'],
    ];
}
```

## Daftar field

| Properti | Wajib | Keterangan |
| --- | --- | --- |
| `$table` | ya | nama tabel di database |
| `$fields` | ya | daftar kolom + aturan validasi |
| `$hidden` | tidak | field yang tidak dikirim ke JSON (mis. `password`) |
| `$relations` | tidak | relasi `belongsTo` / `hasMany` untuk `?with=` |
| `$softDelete` | tidak | `true` = DELETE mengisi `deleted_at`, bukan hapus fisik |

## Opsi per field

```php
'nama' => [
    'type' => 'string',   // string | text | int | bigint | bool | datetime | decimal
    'required' => true,   // wajib diisi saat create
    'max' => 200,         // batas panjang untuk string
    'default' => null,    // nilai default kolom
    'unique' => false,    // buat UNIQUE KEY
    'label' => 'Nama',    // dipakai pada pesan validasi
    'rules' => ['min:3', 'email'], // validasi tambahan via Validator (opsional)
]
```

### Aturan tambahan (`rules`)

Field bisa menambahkan `rules` yang dijalankan `App\Core\Validator` setelah aturan dasar (`required/max/type/unique`):

| Aturan | Contoh | Keterangan |
| --- | --- | --- |
| `min:N` | `min:5` | panjang minimal karakter |
| `email` | `email` | format email |
| `numeric` | `numeric` | angka (int/float) |
| `integer` | `integer` | bilangan bulat |
| `between:min,max` | `between:17,60` | rentang angka |
| `regex:/pola/` | `regex:/^[0-9]{10}$/` | pola regex |
| `enum:a,b,c` | `enum:draft,published` | nilai diizinkan |
| `date` | `date` | tanggal valid |
| `confirmed` | `confirmed` | sama dengan field lain (untuk password) |

Pelanggaran → `422 VALIDATION_ERROR` dengan label field.

Kolom `id`, `created_at`, dan `updated_at` ditambahkan otomatis. Model tidak mendeklarasikan `created_at`. `Schema::sync` menambah kolom yang belum ada dan tidak mengubah tipe kolom yang sudah tersimpan.

Relasi:

```php
protected static array $relations = [
    'user' => ['type' => 'belongsTo', 'model' => User::class, 'foreignKey' => 'user_id'],
];
```

Pakai lewat `?with=user`.

## Langkah setelah menulis Model

1. Daftarkan di `vendor/config/models.php`:

```php
return [
    App\Model\Post::class,
];
```

2. Jalankan:

```bash
php vendor/bin/migrate.php
```

3. Endpoint langsung tersedia:

| Method | Endpoint | Hasil |
| --- | --- | --- |
| GET | `/api/posts` | 200 daftar data |
| GET | `/api/posts/{id}` | 200 / 404 |
| POST | `/api/posts` | 201 / 422 |
| PUT | `/api/posts/{id}` | 200 / 404 / 422 |
| DELETE | `/api/posts/{id}` | 204 / 404 |

## Yang dibuat otomatis

| Bagian | File mesin |
| --- | --- |
| Tabel | `vendor/src/Core/Schema.php` |
| Repository | `vendor/src/Repository/CrudRepository.php` |
| Route | `vendor/config/routes.php` (loop `models.php`) |
| Controller | `vendor/src/Controller/ResourceController.php` |
| Validasi | `vendor/src/Repository/CrudRepository.php` |
| Bentuk JSON | `App\Core\Model::serialize()` |

## Aturan

- Jangan menaruh SQL atau logika bisnis di Model; Model hanya metadata.
- Field baru cukup ditambah di `$fields`, lalu jalankan `migrate.php`.
- Query kustom diletakkan di controller/repository kustom, bukan di Model.
- Nama resource mengikuti `$table`; `posts` menjadi `/api/posts`.