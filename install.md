# Panduan Instalasi — Sekat (framework)

Dokumen ini menjelaskan alur instalasi kernel PHP Sekat. Instalasi mengikuti **alur kerja Sekat yang sudah ada** (`sekat install → db → migrate → serve`), bukan dibangun dari nol.

## Alur kerja Sekat (CLI)

Ini adalah cara resmi dan disarankan:

```powershell
# dari akar proyek framework
sekat install   # siapkan proyek
sekat db        # siapkan database
sekat migrate   # buat tabel (posts, users, dan tabel internal)
sekat serve     # jalankan server
```

Kredensial basis data dibaca dari `.env` (`DB_HOST`, `DB_PORT`, `DB_NAME=framework`, `DB_USER`, `DB_PASS`).

Akun admin hasil seed:
```text
email: admin@example.com
password: admin123
```

## Browser installer (alternatif antarmuka)

Browser installer di `public/install/` adalah **antarmuka web atas alur CLI yang sama**, bukan logika baru dari nol:

| Langkah browser | Setara CLI | Aksi |
|---|---|---|
| Deteksi status | — | Cek `.env` + koneksi DB + tabel `posts` |
| Cek server | — | Cek PHP 8.1+, ekstensi, izin tulis |
| Konfigurasi DB | `sekat db` | Tuliskan `.env` dari input pengguna (`DB_*`) |
| Migrasi & seed | `sekat migrate` | Jalankan `Schema::sync` + seed akun admin default |
| Selesai | `sekat serve` | Arahkan ke `/dashboard` |

Semua rute browser installer dilepasi dari `AuthMiddleware` (belum ada token saat pertama kali).

## Prinsip desain (sesuai AGENTS.md)

- **Satu pintu masuk tetap:** semua request melalui `index.php`.
- **Frontend tidak berisi PHP:** halaman installer adalah HTML statis di `public/install/`, memanggil endpoint JSON API dengan `fetch()`.
- **Controller hanya JSON:** kembali `Response` JSON. Tidak ada `echo`, tidak ada `exit`.
- **Semua rute di `vendor/config/routes.php`:** frontend tidak menyebut nama controller.
- **Kredensial tidak disimpan di kode:** `DB_*` dibaca dari lingkungan (`.env` / environment server).
- **Tidak mengubah pola yang ada:** JSON API, Model-driven CRUD, satu `index.php`.

## Rute API installer

Tambahkan di `vendor/config/routes.php` **sebelum** catch-all `/api/{path:.*}`:

| Metode | Rute | Kontroler | Akses |
|---|---|---|---|
| GET | `/api/install/status` | `InstallController@status` | publik (tanpa auth) |
| GET | `/api/install/check` | `InstallController@check` | publik |
| POST | `/api/install/config` | `InstallController@config` | publik |
| POST | `/api/install/migrate` | `InstallController@migrate` | publik |
| POST | `/api/install/seed` | `InstallController@seed` | publik |

## Contoh InstallController

Tanda tangan sesuai konvensi: `(array $params, Request $request): Response`.

- `status()` — cek `.env` + koneksi DB + tabel `posts`.
- `check()` — cek versi PHP, ekstensi, izin tulis folder.
- `config()` — validasi input, tes koneksi PDO, tulis `.env`.
- `migrate()` — jalankan migrasi + seed (reuse `Database`/`Schema` yang sudah ada, jangan tulis ulang PDO di controller).
- Error lewat `HttpException` (422 untuk validasi, 500 untuk kegagalan server).

## Pertimbangan keamanan

- Installer hanya boleh diakses saat **belum terinstal**. Setelah `.env` valid dan tabel ada, rute installer menolak (403/404) atau diabaikan.
- Jangan log `DB_PASS` atau token ke file log.
- `.env` ditulis dengan izin file yang terbatas (baca-tulis hanya pemilik/proses web).
- Setelah instalasi selesai, disarankan membatasi akses ke `public/install/` di produksi (lewat `.htaccess` atau konfigurasi server).

## Cara menjalankan (development)

```powershell
# dari akar proyek framework
C:\Nxserver\bin\php\php.exe vendor\bin/install-status.php
```

Jika belum terinstal, buka browser ke `http://localhost/framework/install/` dan ikuti langkah installer.

## Catatan implementasi

- Reuse `Database` dan `Schema` yang sudah ada di framework untuk migrasi; jangan menulis ulang koneksi PDO di controller.
- Reuse model `User` dan seed yang sudah ada untuk akun admin default.
- Frontend installer di `public/install/` hanya HTML/JS statis, tidak ada PHP di dalamnya.
