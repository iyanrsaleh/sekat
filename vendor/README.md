# Vendor — Backend

Folder ini adalah **rumah seluruh backend**. Semua PHP aplikasi ada di sini. Frontend tidak boleh menyentuhnya, dan browser tidak perlu membukanya secara langsung.

> Catatan nama: `vendor/` di project ini **bukan** folder dependensi Composer. Dependensi ada di `framework/deps/`.

---

## Titik masuk

| Titik masuk | Dipakai oleh | Alur |
| --- | --- | --- |
| `framework/index.php` | browser | `require vendor/public/index.php` |
| `vendor/bootstrap.php` | web + CLI | autoload bersama (Composer atau fallback PSR-4) |
| `vendor/public/index.php` | bootstrap backend | autoload → config → ErrorHandler → Router |
| `vendor/config/routes.php` | router | daftar seluruh rute frontend + API |
| `vendor/bin/migrate.php` | CLI | buat database, migration, sinkron tabel Model |
| `vendor/bin/seed.php` | CLI | isi data contoh + user admin |
| `vendor/bin/purge.php` | CLI | bersihkan token/log/cache lama |
| `vendor/bin/test.php` | CLI | 15 uji integrasi HTTP |

Alur request:

```text
Browser
  → framework/index.php
    → vendor/public/index.php
      → vendor/config/routes.php
        → App\Controller\...
          → App\Repository\CrudRepository
            → App\Core\Database (MySQL)
      → Response::send() → JSON / HTML halaman
```

Tidak ada titik masuk lain. Jangan membuat `api.php`, `admin.php`, atau file PHP di root project.

---

## Peta folder

| Folder | Isi | README |
| --- | --- | --- |
| `vendor/src/Core/` | mesin: Router, Request, Response, ErrorHandler, Database, Model, Schema | `src/Core/README.md` |
| `vendor/src/Model/` | **definition-only**: tabel + field + aturan validasi | `src/Model/README.md` |
| `vendor/src/Repository/` | akses database (`CrudRepository` + repository kustom) | `src/Repository/README.md` |
| `vendor/src/Controller/` | pengendali request | `src/Controller/README.md` |
| `vendor/src/Middleware/` | auth & lintas-potong (tahap 4) | — |
| `vendor/config/` | aplikasi, database, daftar Model, rute | `config/README.md` |
| `vendor/database/` | migration SQL | `database/README.md` |
| `vendor/bin/` | perintah CLI | `bin/README.md` |
| `vendor/public/` | bootstrap backend (hanya `index.php`) | `public/README.md` |
| `vendor/storage/` | log, cache, upload | `storage/README.md` |

---

## Mulai cepat

### 1. Menambah data baru

Cukup tulis Model di `vendor/src/Model/`:

```php
final class Note extends Model
{
    protected static string $table = 'notes';

    protected static array $fields = [
        'title' => ['type' => 'string', 'max' => 120, 'required' => true, 'label' => 'Judul'],
        'note'  => ['type' => 'text', 'required' => false, 'label' => 'Catatan'],
    ];
}
```

Daftarkan di `vendor/config/models.php`, lalu:

```bash
php vendor/bin/migrate.php
```

Endpoint tersedia otomatis: `/api/notes` (GET/POST/GET id/PUT/DELETE).

### 2. Menambah endpoint kustom

Buat controller di `vendor/src/Controller/`, daftarkan rute di `vendor/config/routes.php` **sebelum** catch-all `/api/{path:.*}`.

### 3. Query sendiri

Pakai `App\Core\Database` dengan parameter ter-bind. Contoh nyata: `vendor/src/Controller/PostSearchController.php`.

---

## Batas tanggung jawab

| Backend (boleh) | Backend (jangan) |
| --- | --- |
| Mengirim JSON | Mengirim HTML halaman |
| Validasi input | Menyusun layout/CSS |
| Query database | Menyentuh file di `framework/public/` |
| Auth, token, log | `echo`/`print` di luar `Response` |
| Membaca file statis untuk `PageController` | Menulis file frontend |

---

## Konfigurasi penting

```php
// vendor/config/app.php
'debug' => true,   // false di produksi
'timezone' => 'Asia/Jakarta',

// vendor/config/database.php
'host' => '127.0.0.1', 'port' => 3310, 'database' => 'framework',
```

---

## Perintah

```bash
php vendor/bin/migrate.php   # database + tabel (termasuk kolom baru)
php vendor/bin/seed.php      # data contoh + admin@example.com / admin123
php vendor/bin/purge.php     # bersihkan token/log/cache
php vendor/bin/test.php      # 15 uji integrasi HTTP
php -S 127.0.0.1:8123 -t . vendor/bin/router.php   # dev server / CI
```

---

## Konvensi

1. Namespace `App\` → `vendor/src/` (PSR-4).
2. Satu Model = satu resource API.
3. Rute hanya di `vendor/config/routes.php`.
4. Error lewat `HttpException`; jangan `throw new \Exception` untuk kesalahan yang bisa dijelaskan ke client.
5. Tidak ada nama file yang di-hardcode untuk halaman frontend; cukup segmen URL + `public/<nama>.html`.
6. Rute tulis (`POST`/`PUT`/`PATCH`/`DELETE`) memakai `[$auth, $adminOnly]`; role disimpan di `users.role`.
7. Daftar resource mendukung `page`, `per_page`, `sort`, `order`, filter per field, dan `with` untuk relasi.
8. Audit log otomatis untuk login/register/logout; baca lewat `/api/audit-logs` (admin).
9. Konfigurasi DB memakai env `DB_*`; `APP_ENV=production` mematikan `debug`.

---

## Uji cepat

```text
GET /framework/                → halaman frontend
GET /framework/about           → public/about.html
GET /framework/api/ping        → {"ok":true,...}
GET /framework/api/posts       → daftar post
GET /framework/api/posts/1     → satu post
GET /framework/api/posts/1?with=user → post + relasi user
GET /framework/api/audit-logs  → log auth (admin)
```