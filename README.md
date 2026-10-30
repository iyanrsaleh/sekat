# Sekat — PHP sebagai Backend API

Status: **1.0.0**. Sepuluh tahap pembangunan sudah selesai. Tahap itu catatan kerja; nomor rilisnya 1.0.0.

---

## 0. Penegasan pembagian kerja

Ini kerangka yang sehat: setiap bagian punya satu tugas, tidak saling menyerobot.

| Bagian | Isi | Tanggung jawab |
| --- | --- | --- |
| `public/` | HTML, CSS, JS | Tampilan, interaksi, memanggil API lewat `fetch()` |
| `vendor/` | PHP, rute, controller, config | Validasi, logika, akses data, mengirim JSON |
| `index.php` | Satu baris bootstrap | Satu-satunya jembatan request ke backend |
| `.htaccess` | Rewrite | URL bersih, asset, API |
| `deps/` | Dependensi Composer | Library pihak ketiga, bukan kode aplikasi |

Aturan yang mengikat:

1. **Frontend tidak berisi PHP.** Tidak ada kode PHP di `public/`.
2. **Backend tidak mengirim halaman HTML.** Controller mengembalikan JSON.
3. **Satu pintu masuk.** Semua request lewat `index.php`; tidak ada endpoint tersebar.
4. **Rute hanya di `vendor/config/routes.php`.** Frontend tidak tahu nama controller.
5. **Halaman frontend hanya dibaca, bukan dirender.** `PageController` meneruskan file `public/<nama>.html`; tidak ada template engine.
6. **Nama halaman tidak di-hardcode.** Segmen URL pertama menjadi nama file, dicari di `public/`.
7. **`vendor/` bukan folder Composer.** Dependensi ada di `deps/`.

---

## 1. Konsep

PHP hanya bekerja di belakang layar. Frontend (HTML/CSS/JS) ditulis terpisah dan memanggil PHP lewat `fetch()` dalam bentuk JSON.

| Peran PHP | Bukan peran PHP |
| --- | --- |
| Router / API endpoint | Render HTML |
| Validasi input | Template engine |
| Akses database / storage | Menulis layout halaman |
| Auth, token | Mengurus layout dan komponen UI |
| Kirim JSON | Menyusun markup halaman |

Alur:

```text
Browser
  ├── GET /framework/            → index.php → public/index.html
  ├── GET /framework/about       → index.php → public/about.html
  ├── GET /framework/assets/...  → public/assets/... (file statis)
  └── GET /framework/api/ping    → vendor/public/index.php → JSON
```

---

## 2. Struktur dan fungsi direktori

```text
framework/
├── public/                     # FRONTEND — tempat kerja user
│   ├── index.html              # + posts, proxy, about, signin, signup, 404
│   ├── dashboard/              # area admin (index, data, uploads)
│   └── assets/
│       ├── css/style.css
│       └── js/*.js             # app, posts, proxy, signin, signup, dashboard, uploads
├── vendor/                     # BACKEND (PHP)
│   ├── bootstrap.php           # autoload bersama web + CLI
│   ├── public/index.php        # entry web (header keamanan + router)
│   ├── src/
│   │   ├── Core/               # Router, Request, Response, ErrorHandler, Database, Model, Schema, RateLimiter, Cache, Mailer
│   │   ├── Controller/         # Resource, Page, Dashboard, System, Auth, Me, Csrf, Upload, Proxy, ApiToken
│   │   ├── Repository/         # Crud, User, Token, EmailToken, ApiToken, Audit
│   │   ├── Model/              # Post, User, AuthToken, AuditLog, EmailToken, ApiToken, ApiGrant
│   │   ├── Middleware/         # Auth, RequireRole, RateLimit, Csrf, ApiToken
│   │   └── Proxy/              # ProxyClient
│   ├── config/                 # app, database, models, routes, proxy, upstreams
│   ├── database/               # migration SQL
│   ├── bin/                    # migrate, seed, purge, test
│   └── storage/                # logs, uploads (per tahun/bulan), cache, mail
├── deps/                       # dependensi Composer (bukan kode aplikasi)
├── index.php                   # SATU pintu masuk: frontend + API
├── composer.json
└── README.md
```

| Path | Fungsi |
| --- | --- |
| `public/` | Seluruh file frontend. Ini file aslinya. |
| `vendor/` | Seluruh kode backend PHP. Tidak pernah dibuka langsung dari browser. |
| `vendor/src/Core/` | Mesin framework: router, request, response, error handler. |
| `vendor/config/` | Konfigurasi aplikasi, database (env-only), daftar Model, rute, proxy & upstreams. |
| `vendor/src/Proxy/` | Klien proxy (meneruskan request ke upstream). |
| `vendor/storage/` | Log, upload (per tahun/bulan), cache, outbox email. |
| `index.php` | Satu pintu masuk: `.htaccess` meneruskan semua URL yang bukan file fisik ke sini. |
| `deps/` | Dependensi Composer (dipindah dari `vendor/` karena nama folder sudah dipakai kode aplikasi). |

> **Penting**: `vendor/` di project ini berisi kode aplikasi, bukan dependensi Composer. Dependensi ada di `deps/` (diatur `config.vendor-dir`).

---

## 3. Menjalankan

Pemasang `sekat` (C++, folder `cli/`) memasang perintah ke komputer dan mengunduh kerangka ke folder proyek:

```text
sekat install --global
cd D:\path\ke\proyek
sekat install
sekat db
sekat migrate
sekat serve
```

Kata sandi basis data dibaca dari `.env` (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`).

Apache Nxserver: `localhost`, `DocumentRoot = C:\Nxserver\www`, `AllowOverride All` (diaktifkan).

`.htaccess` di root project mengarahkan semua URL yang bukan file/folder fisik ke `index.php`:

```apache
RewriteRule ^assets/(.*)$ public/assets/$1 [L]

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

RewriteRule ^ index.php [L,QSA]
```

Frontend cukup memakai path relatif `assets/css/style.css`, `assets/js/app.js`, dan `fetch('api/ping')`. Saat halaman dikirim, `PageController` menyisipkan `<base href="/framework/">` sesuai lokasi project, sehingga path relatif tetap benar walau URL-nya bertingkat (`/about/a/b`).

| Kebutuhan | URL |
| --- | --- |
| Frontend | `http://localhost/framework/` |
| Halaman | `http://localhost/framework/about` |
| Asset | `http://localhost/framework/assets/...` |
| API | `http://localhost/framework/api/ping` |

Contoh respons:

```json
{"ok":true,"data":{"pong":true,"time":"2026-10-28T07:28:34+07:00"}}
```

Frontend memanggil API dengan path relatif:

```js
const response = await fetch('api/ping');
const payload = await response.json();
```

Untuk hosting: upload seluruh folder `framework/` dan jadikan folder itu document root. `.htaccess` ikut terbawa, jadi URL tetap sama.

Composer opsional. Bila `deps/autoload.php` belum ada, `vendor/public/index.php` memakai autoloader cadangan (PSR-4 `App\` → `vendor/src/`).

---

## 4. Routing (semua di `vendor/`)

Root `index.php` hanya bootstrap:

```php
require __DIR__ . '/vendor/public/index.php';
```

Semua rute didefinisikan di `vendor/config/routes.php`:

```php
return static function (Router $router): void {
    // API (didahulukan)
    $router->get('/api/ping', [SystemController::class, 'ping']);
    $router->get('/api/{path:.*}', [SystemController::class, 'notFound']);

    // Frontend dinamis
    $router->get('/', [PageController::class, 'home']);
    $router->get('/{page}', [PageController::class, 'show']);
    $router->get('/{page}/{rest:.*}', [PageController::class, 'show']);
};
```

### Nama halaman tidak di-hardcode

`PageController::show()` mengambil segmen pertama sebagai nama halaman, membersihkannya, lalu mencari `public/<halaman>.html`. Tidak ada daftar nama file di kode.

```php
$page = (string) ($params['page'] ?? 'index');
$page = preg_replace('/[^a-z0-9_-]/i', '', $page);
$path = dirname(__DIR__, 3) . '/public/' . $page . '.html';
```

Menambah halaman cukup menaruh file di `public/`; rute tidak perlu diubah.

| File | URL |
| --- | --- |
| `public/index.html` | `/framework/` |
| `public/about.html` | `/framework/index.php/about` |
| `public/contact.html` | `/framework/contact` |
| `public/about.html` + URL `/about/a/b` | tetap `public/about.html`; `a`, `b` hanya segmen, bukan file |
| file tidak ada, mis. `/framework/asas` | HTTP 404 dengan isi `public/404.html` |

Pencarian file hanya dilakukan sekali, memakai segmen pertama. Segmen berikutnya tidak pernah dipakai untuk mencari file.

### Segmen dinamis di rute

Format `{nama}` menangkap satu segmen, `{nama:regex}` menangkap dengan pola khusus (`{path:.*}` menangkap sisa path). Handler menerima array parameter.

```php
$router->get('/api/posts/{id}', [ResourceController::class, 'show']);
// URL /api/posts/7  →  ['id' => '7']
```

Rute diuji berurutan; `api/*` selalu didaftarkan sebelum catch-all halaman.

### Partials HTML

Halaman bisa memakai bagian bersama (header/footer) lewat atribut `partial` — server meng-inject isi file saat halaman dikirim (bukan `fetch` di browser):

```html
<header partial="./header.html"></header>
```

- Path relatif terhadap folder halaman (`public/` atau `public/dashboard/`).
- Didukung bertingkat (maks 5 level) dan dicegah keluar dari folder web (`..` ditolak).
- Contoh: `public/header.html`, `public/footer.html`, halaman demo `/partial-demo`.

> **Jembatan**: hanya `index.php` di root. `.htaccess` meneruskan URL yang bukan file fisik ke `index.php`, sehingga URL bersih (`/framework/about`, `/framework/api/posts`) berjalan.

---

## 5. Kontrak API

Format respons selalu sama:

```json
{ "ok": true,  "data":  { } }
{ "ok": false, "error": { "code": "NOT_FOUND", "message": "Endpoint tidak ditemukan" } }
```

Aturan:

- Status HTTP: `200`, `201`, `400`, `401`, `403`, `404`, `405`, `422`, `500`.
- Body request: `application/json`.
- Parameter dinamis: `/index.php/api/posts/{id}`.
- Tidak ada HTML dalam respons, termasuk error.

Endpoint yang sudah ada:

| Method | Path | Fungsi | Status |
| --- | --- | --- | --- |
| GET | `/api/ping` | cek server | 200 |
| GET | `/api/posts` | daftar post | 200 |
| GET | `/api/posts/export?format=csv\|xlsx` | ekspor data **(auth admin)** | 200 / 401 / 403 |
| GET | `/api/posts/{id}` | detail post (opsional `?with=user`) | 200 / 404 |
| POST | `/api/posts` | tambah post **(auth admin)** | 201 / 401 / 403 / 422 |
| PUT | `/api/posts/{id}` | ubah post **(auth admin)** | 200 / 401 / 403 / 404 / 422 |
| PATCH | `/api/posts/{id}` | ubah sebagian **(auth admin)** | 200 / 401 / 403 / 404 / 422 |
| DELETE | `/api/posts/{id}` | soft delete post **(auth admin)** | 204 / 401 / 403 / 404 |
| DELETE | `/api/posts/{id}?force=true` | hapus permanen **(auth admin)** | 204 / 401 / 403 / 404 |
| POST | `/api/posts/{id}/restore` | pulihkan soft delete **(auth admin)** | 200 / 401 / 403 / 404 |
| POST | `/api/uploads` | upload gambar (PNG/JPEG/WEBP, ≤2 MB) **(auth admin)** | 201 / 401 / 403 / 422 |
| GET | `/api/uploads` | daftar file **(auth admin)** | 200 / 401 / 403 |
| GET | `/api/uploads/{tahun}/{bulan}/{name}` | ambil file upload (proxy) | 200 / 404 |
| DELETE | `/api/uploads/{tahun}/{bulan}/{name}` | hapus file **(auth admin)** | 204 / 401 / 403 / 404 |

| POST | `/api/auth/login` | login, balas access + refresh token | 200 / 401 / 422 |
| POST | `/api/auth/refresh` | rotasi token dengan `refresh_token` | 200 / 401 / 422 |
| POST | `/api/auth/verify-email` | verifikasi email dengan token | 200 / 400 / 422 |
| POST | `/api/auth/forgot-password` | kirim token reset (di debug: `reset_token`) | 200 / 422 |
| POST | `/api/auth/reset-password` | ganti password dengan token | 200 / 400 / 422 |
| POST | `/api/auth/register` | daftar akun baru, balas access + refresh token | 201 / 422 |
| GET | `/api/csrf` | ambil token CSRF (set cookie `XSRF-TOKEN`) | 200 |
| POST | `/api/auth/logout` | cabut token (auth) | 204 / 401 |
| GET | `/api/me` | data user dari token (auth) | 200 / 401 |
| PUT | `/api/me` | ubah nama/email sendiri **(auth)** | 200 / 401 / 422 |
| POST | `/api/me/logout-all` | cabut semua token **(auth)** | 204 / 401 |
| POST | `/api/me/avatar` | ganti foto profil (multipart `file`, gambar) **(auth)** | 200 / 401 / 422 |
| PUT | `/api/me/password` | ubah password sendiri **(auth)** | 200 / 401 / 422 |
| GET | `/api/audit-logs` | log auth + aktivitas resource **(auth admin)** | 200 / 401 / 403 |
| GET | `/api/stats` | statistik ringkas **(auth admin)** | 200 / 401 / 403 |
| GET | `/api/settings` | pengaturan (admin) | 200 / 401 / 403 |
| GET | `/api/settings/public` | pengaturan publik | 200 |
| PUT | `/api/settings` | simpan pengaturan **(auth admin)** | 200 / 401 / 403 / 422 |

Endpoint dengan tanda **(auth)** wajib header `Authorization: Bearer <token>`; **(auth admin)** juga menuntut `role = admin`.
`GET /api/posts` menerima `?page=1&per_page=20&sort=id&order=desc`, filter per field (`?title=...`), dan `?with=user` untuk menyertakan relasi. Respons berisi `items` + `meta` (`page`, `per_page`, `total`, `pages`).

### Contoh login

```bash
curl -X POST http://localhost/framework/api/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"admin@example.com\",\"password\":\"admin123\"}"
```

```json
{"ok":true,"data":{"token":"...","expires_at":"...","token_type":"Bearer","user":{"id":1,"name":"Admin","email":"admin@example.com"}}}
```

Pemakaian token:

```bash
curl http://localhost/framework/api/me -H "Authorization: Bearer <token>"
```

Contoh `POST /api/posts`:

```bash
curl -X POST http://localhost/framework/api/posts \
  -H "Content-Type: application/json" \
  -d '{"title":"Judul","body":"Isi"}'
```

```json
{"ok":true,"data":{"id":3,"title":"Judul","body":"Isi","created_at":"...","updated_at":"..."}}
```

Validasi: `title` wajib, maksimal 200 karakter; `body` wajib. Pelanggaran → `422 VALIDATION_ERROR`.

### Kode error yang dipakai

| Kode | HTTP | Kapan |
| --- | --- | --- |
| `NOT_FOUND` | 404 | endpoint API tidak dikenal |
| `PAGE_NOT_FOUND` | 404 | halaman frontend tidak ada (fallback `public/404.html`) |
| `POST_NOT_FOUND` | 404 | id post tidak ada |
| `UNAUTHORIZED` | 401 | token tidak ada / tidak valid / kedaluwarsa |
| `INVALID_CREDENTIALS` | 401 | email atau password salah |
| `INVALID_REFRESH_TOKEN` | 401 | refresh token tidak valid / sudah dipakai |
| `INVALID_TOKEN` | 400 | token verifikasi/reset tidak valid atau kedaluwarsa |
| `INVALID_PASSWORD` | 422 | password saat ini salah |
| `TOKEN_MISMATCH` | 403 | token CSRF tidak cocok |
| `EMAIL_TAKEN` | 422 | email sudah terdaftar saat registrasi |
| `FORBIDDEN` | 403 | token valid, tetapi role tidak mencukupi |
| `INVALID_RELATION` | 422 | foreign key menunjuk data yang tidak ada |
| `RELATION_CONFLICT` | 409 | data masih dipakai tabel lain |
| `FILE_REQUIRED`, `FILE_TOO_LARGE`, `FILE_TYPE_NOT_ALLOWED`, `UPLOAD_FAILED` | 422/500 | validasi upload |
| `DUPLICATE_VALUE` | 422 | nilai field unik sudah dipakai |
| `TOO_MANY_REQUESTS` | 429 | melewati batas rate limit login/register |
| `METHOD_NOT_ALLOWED` | 405 | path ada, method salah |
| `INVALID_JSON` | 400 | body bukan JSON valid |
| `VALIDATION_ERROR` | 422 | input tidak lolos validasi |
| `CONTROLLER_NOT_FOUND` | 500 | kelas controller tidak ada |
| `ACTION_NOT_FOUND` | 500 | method controller tidak ada |
| `INTERNAL_ERROR` | 500 | exception tak tertangani |

---

## 5b. Storage (MySQL)

Konfigurasi: `vendor/config/database.php`

```php
return [
    'host' => '127.0.0.1',
    'port' => 3310,
    'database' => 'framework',
    'username' => 'root',
    'password' => 'mysql',
    'charset' => 'utf8mb4',
];
```

Perintah:

```bash
php vendor/bin/migrate.php   # buat database + jalankan migration
php vendor/bin/purge.php      # bersihkan token (login & email), log, cache lama
php vendor/bin/test.php       # 64 uji integrasi HTTP
php -S 127.0.0.1:8123 -t . vendor/bin/router.php   # dev server
php vendor/bin/seed.php      # isi 2 post contoh (hanya bila kosong)
```

Isi:

| File | Fungsi |
| --- | --- |
| `vendor/src/Core/Database.php` | PDO, prepared statement, `select()`/`first()`/`execute()` |
| `vendor/src/Core/Model.php` | basis Model: tabel, field, cast, serialisasi |
| `vendor/src/Core/Schema.php` | membuat tabel dari metadata Model |
| `vendor/src/Repository/CrudRepository.php` | repository generik untuk semua Model |
| `vendor/src/Controller/ResourceController.php` | controller CRUD generik |
| `vendor/src/Model/Post.php` | definisi Model `posts` |
| `vendor/config/models.php` | daftar Model yang dikelola mesin |
| `vendor/database/migrations/*.sql` | migration manual (opsional), dicatat di tabel `migrations` |

Controller tidak pernah menyentuh PDO langsung; hanya repository yang bicara ke database.

---

## 5c. Model-driven: cukup tulis Model

Developer **tidak** membuat repository, controller, route, atau validasi per fitur. Cukup definisikan Model:

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
        'body' => ['type' => 'text', 'required' => true, 'label' => 'Isi'],
    ];
}
```

Daftarkan di `vendor/config/models.php`:

```php
return [App\Model\Post::class];
```

Mesin otomatis menyediakan:

| Yang dibuat mesin | Dari mana |
| --- | --- |
| Tabel database | `Schema::sync()` saat `php vendor/bin/migrate.php` |
| Repository | `CrudRepository` (generik) |
| Route CRUD | `/api/{resource}` dan `/api/{resource}/{id}` |
| Validasi | `required`, `max`, `type`, `label` di `$fields` |
| Serialisasi JSON | `Model::serialize()` (cast tipe + field `$hidden`) |

Tipe field yang didukung: `string`, `text`, `int`, `bigint`, `bool`, `datetime`, `decimal`.

Menambah resource baru (contoh `Note`): buat `vendor/src/Model/Note.php` dengan `$table = 'notes'` dan field-nya, tambahkan ke `models.php`, jalankan `php vendor/bin/migrate.php`. Endpoint `/api/notes` langsung tersedia tanpa menulis controller.
---

## 5d. Query kustom (di luar CRUD otomatis)

Kalau butuh JOIN, LIKE, agregat, atau laporan, tulis SQL sendiri — tetapi tetap lewat `Database` dan prepared statement. Jangan pernah menyambung input user ke string SQL.

Contoh nyata di project: `vendor/src/Controller/PostSearchController.php`

```php
$rows = $this->database->select(
    'SELECT * FROM posts
     WHERE title LIKE :title OR body LIKE :body
     ORDER BY id DESC
     LIMIT 50',
    ['title' => '%' . $keyword . '%', 'body' => '%' . $keyword . '%'],
);
```

Daftarkan rutenya di `vendor/config/routes.php`, **sebelum** rute resource:

```php
$router->get('/api/posts/search', [new PostSearchController($database), 'index']);
```

Uji:

```text
GET /api/posts/search?q=post
```

Aturan:

1. Parameter selalu di-bind (`:nama`), bukan disambung.
2. Satu nama parameter hanya boleh muncul sekali (karena `PDO::ATTR_EMULATE_PREPARES = false`); untuk kondisi yang sama pakai dua nama berbeda.
3. Output tetap memakai `Model::serialize()` agar bentuk JSON konsisten.
4. Query baca berat sebaiknya di repository kustom, bukan di controller, kalau dipakai lebih dari satu tempat.

---

## 5e. Auth (token Bearer)

### Tabel

| Model | Tabel | Exposed |
| --- | --- | --- |
| `User` | `users` | tidak (`/api/users` tidak ada) |
| `AuthToken` | `auth_tokens` | tidak |

Password disimpan dengan `password_hash()`. Token disimpan sebagai hash SHA-256; token asli hanya dikirim sekali saat login.

### Alur

1. `POST /api/auth/register` dengan `{name, email, password}` (password minimal 8 karakter) atau `POST /api/auth/login` dengan `{email, password}`.
2. Server membalas `access_token` (7 hari) + `refresh_token` (90 hari) + `expires_in`.
3. Frontend mengirim header `Authorization: Bearer <token>`.
4. `AuthMiddleware` memvalidasi access token, menempelkan data user ke request.
5. Controller membaca `$request->attribute('user')`.
6. `POST /api/auth/logout` menghapus token dari database.

### Verifikasi email & reset password

- `POST /api/auth/verify-email` dengan `{token}` menandai `users.email_verified_at`. Token dikirim saat register (`verify_token` di debug / lewat email).
- `POST /api/auth/forgot-password` dengan `{email}` → token reset 30 menit. Di debug, respons berisi `reset_token`.
- `POST /api/auth/reset-password` dengan `{token, password}` → ganti password, token langsung tidak berlaku.
- Tanpa SMTP, email ditulis ke `vendor/storage/mail/*.eml`. Bila env SMTP diisi, `App\Core\Mailer` mengirim langsung lewat SMTP (fsockopen, AUTH LOGIN, STARTTLS): `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM`, `MAIL_SECURE`. Path SMTP belum diuji end-to-end karena butuh kredensial.
- Perbandingan kedaluwarsa memakai waktu PHP, bukan `NOW()` database, agar tidak terganggu selisih jam server/database.

### Role

Satu akun satu role. Nama yang sah ada di `vendor/config/roles.php`: `user` dan `admin`. Proyek menambah nama lain di daftar itu. Nama di luar daftar ditolak saat akun dibuat, dan tidak diperlakukan sebagai `admin`.

Halaman daftar dan Google selalu menyimpan `user`, meski body mengirim `role`. Akun `admin` dibuat lewat seed. Tiap proyek memilih halaman setelah masuk.

Rute tulis bawaan (posts, settings, upload) memakai `[$auth, $adminOnly]`:

- tanpa token → `401 UNAUTHORIZED`
- token `user` → `403 FORBIDDEN`
- token `admin` → lanjut

```php
$router->delete('/api/posts/{id}', [$controller, 'destroy'], [$auth, new RequireRole('admin')]);
```

### Melindungi rute

Tambahkan middleware sebagai argumen ketiga:

```php
$router->get('/api/me', [new MeController(), 'show'], [$auth]);
$router->post('/api/auth/logout', [$authController, 'logout'], [$auth]);
```

Artinya: route publik (posts) tetap terbuka, route sensitif memakai `[$auth]`. Jangan lupa mengembalikan `Response` dari middleware bila ingin menghentikan request; `HttpException(401, 'UNAUTHORIZED', ...)` juga sah.

### Seed user

`php vendor/bin/seed.php` membuat user demo bila tabel `users` kosong:

```text
email: admin@example.com
password: admin123
```

Ganti password ini di produksi.

### Catatan header Authorization

Sebagian setup Apache tidak mengisi `$_SERVER['HTTP_AUTHORIZATION']`. `App\Core\Request::header()` membaca `getallheaders()` sebagai cadangan, sehingga token tetap terbaca.

---
## 5f. Keamanan

### Header otomatis

Dikirim untuk semua respons dari `vendor/public/index.php`:

| Header | Nilai |
| --- | --- |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `Referrer-Policy` | `same-origin` |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=()` |
| `Content-Security-Policy` | `default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'` |

CSP `'self'` berarti frontend tidak boleh memakai CDN, inline script, atau inline style. Kalau butuh, ubah kebijakan di `vendor/public/index.php`.

### Rate limit

`RateLimitMiddleware` dipasang pada `POST /api/auth/login` dan `POST /api/auth/register`:

- Batas: 10 request per 60 detik per IP + path.
- Melewati batas → `429 TOO_MANY_REQUESTS`.
- Data hitungan disimpan di `vendor/storage/cache/`.
- Login dengan password benar pun ikut diblok selama jendela waktu berjalan.

Menaikkan batas:

```php
new RateLimitMiddleware(new RateLimiter($dir), 20, 60);
```

### Akses file

| URL | Hasil |
| --- | --- |
| `/framework/vendor/...` | 403 |
| `/framework/deps/...` | 403 |

`vendor/.htaccess` dan `deps/.htaccess` menolak semua akses. `.htaccess` di root juga menolak `vendor/`, `deps/`, dotfiles (`.git`, `.env`), `composer.json`, `composer.lock`, `README.md`, dan berkas berakhiran `.sql/.log/.pem/.key/.bak/.bat/.sh` (403) — berlapis, jadi tetap aman walau konfigurasi `AllowOverride` berubah.

Password database **tidak disimpan di file** — wajib lewat env `DB_PASS` (dari environment server/panel, atau `SetEnv DB_PASS` lokal di `.htaccess` untuk pengembangan). Ini supaya penyerang ber-RCE yang membaca file (seperti pada temuan situs lain) tidak mendapat kredensial DB. Di produksi tanpa `DB_PASS`, aplikasi menolak berjalan.

### Rahasia dan token

- Password: `password_hash()`; verifikasi `password_verify()`.
- Token: 32 byte acak, disimpan sebagai hash SHA-256; token asli hanya dikirim sekali saat login.
- Access token berlaku 7 hari, refresh token 90 hari. Frontend otomatis memanggil `/api/auth/refresh` saat menerima 401, jadi user yang aktif tidak dipaksa login ulang.
- Logout menghapus token dari database.
- Jangan menaruh token di URL; gunakan header `Authorization`.

### CSRF & cookie refresh

- `GET /api/csrf` mengembalikan token dan menyimpan cookie `XSRF-TOKEN` (double-submit).
- `CsrfMiddleware` di rute tulis: **hanya** memaksa pencocokan cookie `XSRF-TOKEN` dengan header `X-XSRF-TOKEN` bila cookie ada. Request dengan `Authorization: Bearer` dilewati (tidak rawan CSRF).
- Ketidakcocokan → `403 TOKEN_MISMATCH`.

Mode **cookie refresh** (opsional, default mati):

```powershell
$env:AUTH_COOKIE_REFRESH='1'   # lalu restart/isi ulang request
```

Saat aktif: login tidak lagi membalas `refresh_token`; token tersebut disimpan di cookie httpOnly `refresh_token` (SameSite=Lax via atribut), dan `POST /api/auth/refresh` membacanya dari cookie. Access token tetap dikirim sebagai Bearer.

### Sebelum produksi

1. Set `'debug' => false` di `vendor/config/app.php`.
2. Ganti password user seed (`admin@example.com / admin123`) atau hapus user tersebut.
3. Ganti user MySQL ke akun terbatas (bukan `root`).
4. Pakai HTTPS.
5. Jalankan `php vendor/bin/purge.php` berkala (cron/scheduler).
6. Backup database dan `vendor/storage/uploads/`.
7. Jalankan `php vendor/bin/test.php` setelah deploy.
7. Bila endpoint posts harus privat, tambahkan middleware auth:

```php
$router->post('/api/' . $resource, [$controller, 'store'], [$auth]);
```

---
## 5g. Relasi, audit log, transaksi, dan environment

### Relasi Model

Relasi dideklarasikan di Model, diambil lewat `?with=`:

```php
protected static array $relations = [
    'user' => ['type' => 'belongsTo', 'model' => User::class, 'foreignKey' => 'user_id'],
];
```

| Tipe | Arti |
| --- | --- |
| `belongsTo` | ambil satu baris dari Model lain memakai FK di baris ini |
| `hasMany` | ambil semua baris Model lain yang FK-nya menunjuk baris ini |

```text
GET /api/posts/19?with=user
GET /api/posts?with=user
```

Field `$hidden` pada Model relasi tetap dihormati (password user tidak ikut).

### Audit log

Tabel `audit_logs` mencatat `auth.login`, `auth.login_failed`, `auth.register`, `auth.logout` beserta user, IP, dan metadata. Hanya admin yang bisa membacanya:

```text
GET /api/audit-logs?per_page=20
```

### Transaksi

```php
$database->transaction(function (Database $db): void {
    $db->execute('UPDATE ...');
    $db->execute('INSERT ...');
});
```

Bila salah satu query gagal, seluruh perubahan dibatalkan (`rollBack`).

### Environment

Semua konfigurasi bisa diatur lewat variabel lingkungan (tanpa mengubah file):

| Variabel | Default |
| --- | --- |
| `APP_ENV` | `local` (`production` mematikan `debug`) |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3310` |
| `DB_NAME` | `framework` |
| `DB_USER` | `root` |
| `DB_PASS` | **wajib env** (tanpa default; produksi menolak bila kosong) |

Lokal: `DB_PASS` diisi lewat `SetEnv` di `.htaccess` (untuk pengembangan) atau `$env:DB_PASS`. Produksi: set di environment server/panel, bukan di file.

Contoh:

```powershell
$env:APP_ENV='production'; $env:DB_PASS='rahasia'; php vendor/bin/migrate.php
```

### Caching

Daftar resource di-cache 15 detik (`App\Core\Cache`, namespace `crud/<resource>`). Setiap POST/PUT/PATCH/DELETE otomatis menghapus cache resource terkait, jadi tidak ada data basi.

```php
Cache::remember('crud/posts', $key, 15, fn () => $data);
Cache::flush('crud/posts');
```

### Dev server & CI

```bash
php -S 127.0.0.1:8123 -t . vendor/bin/router.php
TEST_BASE=http://127.0.0.1:8123 php vendor/bin/test.php
```

`.github/workflows/ci.yml` menjalankan lint, migrate, seed, server, dan test otomatis (MySQL 8.4 sebagai service).

---
## 5h. Soft delete dan upload

### Soft delete

Model dengan `protected static bool $softDelete = true;` (dipakai `Post`):

| Aksi | Efek |
| --- | --- |
| `DELETE /api/posts/{id}` | mengisi `deleted_at`, data tetap ada |
| `GET /api/posts/{id}` | 404 karena baris terhapus disaring |
| `GET /api/posts` | baris terhapus tidak muncul |
| `POST /api/posts/{id}/restore` | `deleted_at = NULL` |
| `DELETE /api/posts/{id}?force=true` | hapus fisik |

Kolom `deleted_at` ditambahkan otomatis oleh `Schema::sync()`.

### Foreign key

Relasi `belongsTo` menghasilkan FK otomatis saat migrate:

```text
relasi: fk_posts_user_id
```

`POST` dengan `user_id` yang tidak ada → `422 INVALID_RELATION`. FK memakai `ON DELETE SET NULL` untuk kolom opsional dan `RESTRICT` untuk kolom wajib.

### Upload

```bash
curl -X POST http://localhost/framework/api/uploads \
  -H "Authorization: Bearer <token-admin>" \
  -F "file=@foto.png"
```

```json
{"ok":true,"data":{"name":"4de2...png","url":"api/uploads/4de2...png","mime":"image/png","size":70}}
```

Aturan:

- Hanya PNG/JPEG/WEBP, maksimal 2 MB.
- Nama file dibuat ulang (32 hex + ekstensi), nama asli user dibuang.
- File disimpan di `vendor/storage/uploads/{Tahun}/{Bulan}/` (mis. `2026/10`) agar folder rapi.
- Akses hanya lewat proxy `GET /api/uploads/{tahun}/{bulan}/{name}`; folder `vendor/storage/uploads` tidak bisa dibuka dari browser. Upload/daftar/hapus wajib admin.

Daftar dan hapus:

```bash
curl -H "Authorization: Bearer <token-admin>" http://localhost/framework/api/uploads
curl -X DELETE -H "Authorization: Bearer <token-admin>" http://localhost/framework/api/uploads/<nama-file>
```

Contoh halaman: `/dashboard/uploads` (unggah, daftar gambar, hapus).

---
## 5i. Token API (klien)

Token berdiri sendiri untuk klien/mesin, tidak terikat akun user. Meniru konsep middleware pada referensi:

- Rahasia diawali `NX_`, disimpan hanya sebagai hash SHA-256.
- Masa berlaku diatur lewat `days`, `months`, atau `years` (cukup satu). Tanpa ketiganya, token tidak kedaluwarsa.
- Hak akses lewat **grant** (token → resource). Resource dilindungi `ApiTokenMiddleware`.

### Pengelolaan (admin)

| Metode | Endpoint | Fungsi |
| --- | --- | --- |
| GET | `/api/api-tokens` | daftar nama + `expires_at` (tanpa rahasia) |
| POST | `/api/api-tokens` | buat token `{name, days?/months?/years?, token?}` → `201` rahasia sekali |
| DELETE | `/api/api-tokens/{name}` | cabut token + grant-nya |
| GET | `/api/api-grants` | daftar grant |
| POST | `/api/api-grants` | `{token, kind:'api', name:'posts'}` |
| DELETE | `/api/api-grants/{name}/{kind}/{resource}` | hapus satu grant |

Contoh:

```bash
# buat token 30 hari
curl -X POST http://framework.local/api/api-tokens \
  -H "Content-Type: application/json" -H "Authorization: Bearer <token-admin>" \
  -d '{"name":"klien","days":30}'
# → {"name":"klien","token":"NX_345d64bc299521f5","expires":"..."}

# beri hak akses resource protected
curl -X POST http://framework.local/api/api-grants \
  -H "Content-Type: application/json" -H "Authorization: Bearer <token-admin>" \
  -d '{"token":"klien","kind":"api","name":"protected"}'
```

### Melindungi resource

```php
$router->get('/api/protected', [$system, 'ping'], [
    new ApiTokenMiddleware($apiTokens, 'api', 'protected'),
]);
```

Tanpa grant → `403 FORBIDDEN`; token tidak valid/kedaluwarsa → `401 UNAUTHORIZED`.

Token pertama otomatis mendapat grant `middleware/middleware`.

---
## 5j. Proxy

Meneruskan permintaan ke mesin lain; pihak luar hanya melihat `/api/{alias}`.

| File | Isi |
| --- | --- |
| `vendor/config/proxy.php` | daftar alias (`feed`, `self`) |
| `vendor/config/upstreams.php` | alias => URL tujuan (tidak diekspos) |
| `vendor/src/Proxy/ProxyClient.php` | meneruskan lewat curl, atau soket IPv4 bila curl tidak ada |
| `vendor/src/Controller/ProxyController.php` | route `/api/{alias}/{path}` |

Contoh:

```text
GET /api/feed/posts/1  →  GET https://mesin-lain.example/posts/1
```

- Metode & badan diteruskan; `Authorization` tidak.
- `OPTIONS` → 204 lokal; `..` ditolak.
- Alias tidak terdaftar → 404 (tidak diklaim, `/api/posts` tetap milik API).
- Tujuan tidak menjawab → 502; belum punya tujuan → 503.
- `NX_PROXY_UPSTREAMS` (JSON) menimpa `upstreams.php`.

Menambah alias cukup dua file config, rute dibuat otomatis. Lihat `vendor/src/Proxy/README.md`.

---
## 6. Menambah resource dan endpoint kustom

### Resource baru (otomatis)

1. Buat Model di `vendor/src/Model/Note.php`:

```php
final class Note extends Model
{
    protected static string $table = 'notes';

    protected static array $fields = [
        'title' => ['type' => 'string', 'max' => 120, 'required' => true, 'label' => 'Judul'],
        'note' => ['type' => 'text', 'required' => false, 'label' => 'Catatan'],
    ];
}
```

2. Daftarkan di `vendor/config/models.php`:

```php
return [
    App\Model\Post::class,
    App\Model\Note::class,
];
```

3. Jalankan `php vendor/bin/migrate.php`.

Endpoint `/api/notes` (GET/POST/GET id/PUT/DELETE) langsung tersedia.

### Endpoint kustom (di luar CRUD)

Tambahkan rute manual di `vendor/config/routes.php` sebelum catch-all `/api/{path:.*}`:

```php
$router->get('/api/laporan', [LaporanController::class, 'index']);
```

Contoh nyata di project: `/api/stats` di `App\Controller\SystemController::stats()` (auth + admin).

Handler boleh mengembalikan `Response`, `array` (dibungkus JSON otomatis), atau `null`.

---

## 7. Lapisan dan tanggung jawab

| Lapisan | File | Tugas |
| --- | --- | --- |
| Router | `vendor/src/Core/Router.php` | cocokkan method + path, ambil parameter, panggil handler |
| Request | `vendor/src/Core/Request.php` | method, path, query, header, bearer token, JSON body |
| Response | `vendor/src/Core/Response.php` | bungkus JSON, status code, header |
| ErrorHandler | `vendor/src/Core/ErrorHandler.php` | ubah exception jadi JSON, tulis log |
| HttpException | `vendor/src/Core/HttpException.php` | error terkontrol dengan kode + status |
| Model | `vendor/src/Model/` | definisi tabel + field + aturan validasi |
| Repository | `vendor/src/Repository/CrudRepository.php` | satu-satunya yang bicara ke database |
| Middleware | `vendor/src/Middleware/AuthMiddleware.php` | validasi token Bearer, menempelkan user ke request |

Contoh pemakaian `Request`:

```php
$name = $request->query('name', 'anonim');
$token = $request->bearerToken();
$data = $request->json();   // array, atau HttpException 400 bila JSON rusak
```

---

## 8. Rencana kerja

### Tahap 1 — Kerangka minimal ✅
- [x] Router (GET/POST/PUT/PATCH/DELETE) + parameter `{id}`
- [x] `Request`, `Response` JSON, `HttpException`, `ErrorHandler`
- [x] Log error ke `vendor/storage/logs/app-YYYY-MM-DD.log`
- [x] Entry tunggal `index.php` (frontend + API via PATH_INFO)
- [x] `GET /index.php/api/ping`

### Tahap 2 — Storage ✅
- [x] `vendor/config/database.php` → MySQL `127.0.0.1:3310`, database `framework`
- [x] `App\Core\Database` (PDO, prepared statement, fetch assoc)
- [x] `Model` metadata + `Schema` + `CrudRepository` generik (model-driven)
- [x] Migration `posts` via `php vendor/bin/migrate.php`
- [x] Seed contoh via `php vendor/bin/seed.php`
- [x] Endpoint baca: `GET /api/posts`, `GET /api/posts/{id}`

### Tahap 3 — CRUD ✅
- [x] `ResourceController` generik: index, show, store, update, destroy untuk semua Model
- [x] Validasi otomatis dari metadata Model (`VALIDATION_ERROR`, 422)
- [x] Uji semua endpoint: 201, 200, 204, 404, 422

### Tahap 4 — Auth ✅
- [x] Tabel `users` + `auth_tokens` (Model `$exposed = false`)
- [x] `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/me`
- [x] `AuthMiddleware` + dukungan middleware di `Router`
- [x] Password `password_hash()`, token acak + hash SHA-256, masa 7 hari

### Tahap 5 — Frontend
- [x] Contoh CRUD: `public/posts.html` + `assets/js/posts.js` (buka `/framework/posts`)
- [x] Contoh sign in: `public/signin.html` + `assets/js/signin.js` (token di `localStorage`, logout mencabut token)
- [x] Contoh sign up: `public/signup.html` + `assets/js/signup.js` (`POST api/auth/register`, token langsung diberikan)
- [x] Daftar, tambah, ubah, hapus lewat `fetch('api/posts')`
- [ ] Desain final milik user

### Tahap 6 — Pengerasan ✅
- [x] Header keamanan: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP
- [x] Rate limit login/register: 10 request / 60 detik per IP+path (`429 TOO_MANY_REQUESTS`)
- [x] `vendor/`, `deps/`, berkas sensitif, dan dotfiles ditolak akses web (403)
- [x] `php vendor/bin/purge.php` membersihkan token, log, dan cache lama
- [x] `debug` otomatis `false` saat `APP_ENV=production`
- [x] Role `admin`/`user` + `RequireRole`; tulis posts hanya untuk admin
- [x] Pagination (`page`, `per_page`), sort, order, filter per field
- [x] Validasi `unique` → `422 DUPLICATE_VALUE`, termasuk penanganan error constraint DB
- [x] `Schema::sync()` menambah kolom/index baru tanpa menghapus data
- [x] Bootstrap bersama `vendor/bootstrap.php` (web + CLI)
- [x] `php vendor/bin/test.php` — 26 uji integrasi

### Tahap 7 — Lanjutan ✅
- [x] Relasi `belongsTo`/`hasMany` via `?with=`
- [x] Audit log `audit_logs` (auth + CRUD) + `GET /api/audit-logs` (admin)
- [x] Helper transaksi `Database::transaction()`
- [x] Konfigurasi lewat env (`APP_ENV`, `DB_*`)
- [x] Dev server `vendor/bin/router.php` + CI GitHub Actions

### Tahap 8 — Data & file ✅
- [x] Soft delete (`deleted_at`) + `restore` + `?force=true`
- [x] Foreign key otomatis untuk relasi `belongsTo`
- [x] Error FK/constraint dipetakan ke 422/409
- [x] Upload gambar (PNG/JPEG/WEBP ≤2 MB) + daftar + hapus
- [x] `php vendor/bin/test.php` — 26 uji

### Tahap 9 — Auth & performa ✅
- [x] Access token (7 hari) + refresh token (90 hari) dengan rotasi
- [x] `POST /api/auth/refresh`; refresh lama langsung tidak berlaku
- [x] Caching daftar resource (`App\Core\Cache`, TTL 15 detik) + invalidasi otomatis saat tulis
- [x] Audit CRUD resource (posts.create/update/delete/restore)
- [x] Area admin `/dashboard` terpisah dari halaman publik (`DashboardController` → `public/dashboard/`)
- [x] Validator terpusat (`App\Core\Validator`): email, min/max, numeric, between, regex, enum, date, confirmed — dipakai via field `rules`
- [x] Exporter CSV/XLSX (`GET /api/{resource}/export`)
- [x] Pengaturan dasar (tabel `settings`): `GET/PUT /api/settings`, halaman `/dashboard/settings`, judul halaman otomatis dari `site_title`
- [x] Profil: ganti foto (`POST /api/me/avatar`) + ubah password (`PUT /api/me/password`) + edit nama/email (`PUT /api/me`) + logout semua perangkat
- [x] Partials HTML: elemen `partial="./file.html"` di-inject server saat halaman dikirim
- [x] Verifikasi email + reset password (token + Mailer outbox)
- [x] CSRF double-submit (`GET /api/csrf`, middleware Bearer-exempt)
- [x] `GET /api/stats` + area admin `/dashboard` (folder `public/dashboard/`)
- [x] Halaman contoh upload (`/dashboard/uploads`)
- [x] Token API klien: nama + `days`/`months`/`years` + grant (meniru middleware)
- [x] Proxy: `/api/{alias}/{path}` diteruskan ke URL tujuan (meniru modul proxy)

---

## 9. Keputusan yang sudah disepakati

| Topik | Keputusan |
| --- | --- |
| Folder backend | `vendor/` (kode aplikasi), dependensi Composer di `deps/` |
| Storage | MySQL/MariaDB via PDO — `127.0.0.1:3310`, database `framework`, user `root` |
| Auth | Token Bearer, header `Authorization: Bearer <token>`, token 7 hari disimpan sebagai hash |
| Frontend & API | Satu domain, tanpa CORS |
| Composer | PSR-4 `App\` → `vendor/src/` |
| Entry | Satu file `index.php`, memakai PATH_INFO |

### Tahap 10 — Alamat, proxy, dan skrip halaman ✅
- [x] `Request::appOrigin()` menyusun akar situs dari host dan letak `index.php`
- [x] Halaman publik dan dashboard mengganti `__APP_ORIGIN__` dengan akar itu
- [x] Tautan reset password memakai akar yang sama
- [x] Tautan dashboard tetap relatif terhadap `<base>`, tidak diawali `/`
- [x] Menu awal: Dashboard, Settings, situs publik. Halaman Data dan Upload ada di kelompok Contoh
- [x] `ProxyClient` menyambung IPv4 bila curl tidak ada, batas waktu bisa diatur, `postJson()` mengirim header pemanggil
- [x] Rute proxy publik tetap membuang `Authorization`
- [x] `spa.js` mengambil nama modul dari nama berkas. Skrip halaman mendaftarkan `window.nxPages` dan memanggilnya di baris terakhir
- [x] Catatan `Schema::sync`: kolom baru ditambah, tipe kolom lama tidak diubah; model tidak menulis `created_at`

Yang perlu diputuskan selanjutnya:

1. Uji end-to-end SMTP dengan kredensial nyata.
2. Aktifkan mode cookie refresh (`AUTH_COOKIE_REFRESH=1`) bila ingin refresh token jauh dari JS.

---

## 10. Yang sengaja tidak dibuat

- Template engine (Twig/Blade) dan folder `views/`.
- Helper `view()`, `layout()`, `partial()`.
- Server-side rendering.
- Panel admin; desain final frontend di `public/` ditulis sendiri oleh user (framework menyediakan mesin backend + contoh).