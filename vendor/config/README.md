# Config

Semua konfigurasi aplikasi. Satu file = satu tanggung jawab. Tidak ada logika di sini, hanya data yang dibaca mesin.

## Isi

| File | Dibaca oleh | Isi |
| --- | --- | --- |
| `app.php` | `vendor/public/index.php` | nama aplikasi, versi, `debug`, `timezone` |
| `database.php` | `Database::fromConfigFile()`, `bin/migrate.php` | host, port, nama database, user, password, charset |
| `models.php` | `vendor/config/routes.php`, `bin/migrate.php` | daftar Model yang dikelola mesin |
| `routes.php` | `vendor/public/index.php` | semua rute frontend dan API |
| `proxy.php`, `upstreams.php` | route proxy | daftar alias proxy + URL tujuan |

## app.php

```php
$environment = getenv('APP_ENV') ?: 'local';
$isProduction = $environment === 'production';

return [
    'name' => 'Sekat',
    'version' => '1.0.0',
    'env' => $environment,
    'debug' => !$isProduction,   // APP_ENV=production → debug mati otomatis
    'timezone' => 'Asia/Jakarta',
];
```

## database.php

Nilai diambil dari env, dengan default untuk mesin lokal:

| Variabel | Default |
| --- | --- |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3310` |
| `DB_NAME` | `framework` |
| `DB_USER` | `root` |
| `DB_PASS` | **wajib env** (tanpa default; produksi menolak bila kosong) |

```powershell
$env:DB_PASS='rahasia'; php vendor/bin/migrate.php
```

## models.php

```php
return [
    App\Model\Post::class,
];
```

Model yang terdaftar otomatis mendapat tabel (via `migrate.php`) dan route CRUD `/api/{resource}`.

## routes.php

```php
return static function (Router $router): void {
    // API sistem
    // Query kustom (sebelum resource)
    // Auto CRUD: loop models.php
    // Catch-all API 404
    // Frontend dinamis (PageController)
};
```

Urutan penting:

1. Rute spesifik (`/api/ping`, `/api/posts/search`)
2. Rute resource (`/api/posts`, `/api/posts/{id}`)
3. Catch-all API (`/api/{path:.*}`)
4. Halaman frontend (`/{page}`, `/{page}/{rest:.*}`)

## Aturan

1. Jangan menaruh kredensial di kode aplikasi selain file ini.
2. Di produksi: `debug => false` dan ganti user MySQL ke akun terbatas.
3. File ini tidak boleh mengembalikan output; selalu `return` array/closure.
4. Menambah Model cukup di `models.php`; tidak perlu menyentuh rute.