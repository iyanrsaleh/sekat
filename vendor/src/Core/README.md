# Core

Mesin framework. Tidak berisi fitur aplikasi — hanya alat yang dipakai lapisan lain.

## Isi

| File | Kelas | Tugas |
| --- | --- | --- |
| `Router.php` | `App\Core\Router` | mencocokkan method + path, mengambil parameter, memanggil handler |
| `Request.php` | `App\Core\Request` | method, path, query, header, bearer token, body JSON, file upload, `appOrigin()` |
| `Response.php` | `App\Core\Response` | `json()`, `html()`, `file()`, `error()`, `noContent()` + status code |
| `HttpException.php` | `App\Core\HttpException` | error terkontrol (kode + status) yang dikirim sebagai JSON |
| `ErrorHandler.php` | `App\Core\ErrorHandler` | menangkap exception, menulis log, mengirim JSON error |
| `Database.php` | `App\Core\Database` | PDO, `select()`, `first()`, `execute()`, `lastInsertId()`, `transaction()` |
| `Model.php` | `App\Core\Model` | basis Model: `$table`, `$fields`, `$hidden`, cast, serialisasi |
| `Schema.php` | `App\Core\Schema` | membuat tabel + menambah kolom/index baru dari metadata Model |
| `RateLimiter.php` | `App\Core\RateLimiter` | pembatas request berbasis file (login/register) |
| `Cache.php` | `App\Core\Cache` | cache file dengan namespace + TTL (`remember`, `flush`) |
| `Partials.php` | `App\Core\Partials` | inject partial HTML (`partial="./header.html"`) saat halaman dikirim |
| `Validator.php` | `App\Core\Validator` | validasi terpusat (`email`, `min/max`, `numeric`, `between`, `regex`, `enum`, `date`, `confirmed`) |
| `Exporter.php` | `App\Core\Exporter` | ekspor data ke CSV / XLSX |
| `Settings.php` | `App\Core\Settings` | akses pengaturan dasar (judul halaman) |
| `Middleware.php` | `App\Core\Middleware` | kontrak middleware (`handle(Request): ?Response`) |

## Alur request

```text
index.php
  └── vendor/public/index.php
        ├── ErrorHandler::register()
        ├── Router (rute dari vendor/config/routes.php)
        └── Response::send()
```

## Contoh

```php
$router->get('/api/posts/{id}', [new ResourceController($model, $repo), 'show']);

$request->query('q', '');
$request->json();          // 400 INVALID_JSON bila rusak
$request->bearerToken();   // string|null

return Response::json(['ok' => true]);
throw new HttpException(422, 'VALIDATION_ERROR', 'Judul wajib diisi');
```

## Aturan

1. Core tidak boleh bergantung pada `App\Controller` atau `App\Model` tertentu.
2. Semua output ke browser lewat `Response`; tidak ada `echo` langsung.
3. Query database hanya lewat `Database` dengan parameter ter-bind.
4. Error tidak pernah dibocorkan mentah saat `debug = false`.

## Catatan teknis

- `PDO::ATTR_EMULATE_PREPARES = false`: satu nama parameter tidak boleh dipakai dua kali dalam satu query.
- Router mendukung `{nama}` dan `{nama:regex}`; rute diuji berurutan.
- `Model::serialize()` adalah satu-satunya tempat bentuk JSON resource ditentukan.