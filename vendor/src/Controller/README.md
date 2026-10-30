# Controller

Folder ini berisi pengendali request. Ada dua jenis:

1. **Controller generik** — melayani semua Model, tidak perlu ditulis per fitur.
2. **Controller kustom** — hanya untuk kebutuhan khusus (halaman, query, auth, laporan).

## Controller generik

| File | Tugas |
| --- | --- |
| `ResourceController.php` | CRUD semua resource: `index`, `show`, `store`, `update`, `destroy` |

`ResourceController` dipasang otomatis di `vendor/config/routes.php` untuk setiap Model di `models.php`. Jadi menambah Model tidak memerlukan controller baru.

## Controller yang sudah ada

| File | Endpoint | Tugas |
| --- | --- | --- |
| `PageController.php` | `/{halaman}` | mengirim `public/<halaman>.html`, fallback `public/404.html`, menyisipkan `<base>`, mengganti `__APP_ORIGIN__` |
| `SystemController.php` | `/api/ping`, `/api/stats` | health check + statistik (admin) |
| `SystemController.php` | `/api/*` (tidak dikenal) | 404 JSON `NOT_FOUND` |
| `ResourceController.php` | `/api/{resource}`, `/api/{resource}/export` | CRUD otomatis + ekspor CSV/XLSX |
| `PostSearchController.php` | `/api/posts/search?q=` | contoh query kustom dengan `LIKE` |
| `UploadController.php` | `POST/GET /api/uploads`, `GET/DELETE /api/uploads/{name}` | upload, daftar, ambil, hapus (admin) |
| `DashboardController.php` | `/dashboard`, `/dashboard/{page}` | area admin terpisah dari halaman publik |
| `SettingsController.php` | `/api/settings`, `/api/settings/public` | pengaturan dasar aplikasi |
| `MeController.php` | `/api/me` (GET/PUT), `/api/me/avatar`, `/api/me/password`, `/api/me/logout-all` | profil: data, nama/email, foto, password, logout semua |
| `AuthController.php` | `/api/auth/login`, `/register`, `/refresh`, `/logout` | autentikasi + rotasi token |
| `ApiTokenController.php` | `/api/api-tokens`, `/api/api-grants` | kelola token klien + grant |

## Bentuk controller

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;

final class ContohController
{
    public function index(array $params, Request $request): Response
    {
        $q = (string) $request->query('q', '');

        return Response::json(['q' => $q, 'items' => []]);
    }
}
```

## Aturan penulisan

1. **Selalu kembalikan `Response`.** Jangan `echo`, jangan cetak HTML, jangan `exit`.
2. **Urutan argumen:** `(array $params, Request $request)`.
   - `$params` berisi segmen dinamis, mis. `['id' => '7']`.
   - `$request` opsional; hilangkan bila tidak dipakai.
3. **Error pakai `HttpException`** supaya keluar sebagai JSON:

```php
throw new HttpException(422, 'VALIDATION_ERROR', 'Judul wajib diisi');
```

4. **Akses data lewat repository**, bukan `new PDO`. Controller kustom boleh memakai `App\Core\Database` untuk query khusus.
5. **Jangan menaruh query di controller** bila dipakai di beberapa tempat — pindahkan ke repository.
6. **Tidak ada pengecekan auth di sini**; itu tugas middleware (Tahap 4).

## Menambah controller kustom

1. Buat file di folder ini.
2. Daftarkan rute di `vendor/config/routes.php`, **sebelum** catch-all `/api/{path:.*}` dan sebelum rute resource bila path-nya bertabrakan:

```php
$router->get('/api/laporan', [new LaporanController($database), 'index']);
```

3. Uji dengan `curl` atau browser.

## Kelas pendukung

| Kelas | Fungsi |
| --- | --- |
| `App\Core\Response` | `json()`, `html()`, `error()`, `noContent()` |
| `App\Core\Request` | `query()`, `json()`, `header()`, `bearerToken()` |
| `App\Core\HttpException` | error terkontrol dengan kode + status |
| `App\Repository\CrudRepository` | operasi database generik |
| `App\Core\Database` | `select()`, `first()`, `execute()` |