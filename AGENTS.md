# Aturan kerja agen — Sekat

Berlaku untuk setiap perubahan di folder ini. Sekat 1.1.0 adalah kerangka PHP 8.1: backend API JSON di `vendor/`, tampilan di `public/`. Rincian ada di `README.md` dan `README.md` folder yang disentuh. Baca README folder itu sebelum mengubah isinya.

## Pembagian kerja

| Bagian | Tugas |
| --- | --- |
| `public/` | HTML, CSS, JS. Memanggil API dengan `fetch()` |
| `vendor/` | PHP aplikasi: rute, controller, model, repository, middleware |
| `deps/` | Dependensi Composer. Bukan kode aplikasi |
| `index.php` | Satu pintu masuk. Isinya hanya memuat `vendor/public/index.php` |
| `cli/` | Pemasang C++ perintah `sekat`. Bukan mesin web |

Aturan yang mengikat:

1. Frontend tidak berisi PHP. Jangan taruh kode PHP di `public/`.
2. Controller tidak mengirim HTML. Kembalikan `Response` JSON. Jangan `echo`, jangan `exit`.
3. Semua request lewat `index.php`. Jangan buat endpoint tersebar.
4. Rute hanya di `vendor/config/routes.php`. Frontend tidak menyebut nama controller.
5. Halaman hanya dibaca. `PageController` mengirim `public/<nama>.html`. Tidak ada template engine, `views/`, atau server-side rendering.
6. Nama halaman tidak di-hardcode. Segmen URL pertama menjadi nama file di `public/`.
7. `vendor/` adalah kode aplikasi. Jangan memasang paket Composer ke folder itu.

## Cara menambah fitur

CRUD baru cukup Model. Jangan menulis controller, repository, atau rute per tabel.

1. Buat `vendor/src/Model/<Nama>.php` (`$table`, `$fields`). Jangan deklarasikan `id`, `created_at`, atau `updated_at`.
2. Daftarkan kelasnya di `vendor/config/models.php`.
3. Jalankan `php vendor/bin/migrate.php` dari akar proyek.

`Schema::sync` menambah kolom dan indeks yang belum ada. Ia tidak mengubah tipe kolom yang sudah tersimpan dan tidak menghapus data. Model dengan `$exposed = false` tetap dimigrasi, tetapi tidak mendapat rute CRUD publik. Pakai itu untuk users, token, dan tabel internal.

Model yang punya field `slug` bersama `title` mendapat tautan `/{slug}`. Mesin mengisi slug dari judul, menjaga nama halaman yang sudah ada, dan menambahkan tag Open Graph pada halaman yang dikirim. Jangan menaruh id di alamat publik.

Endpoint di luar CRUD:

1. Controller di `vendor/src/Controller/`. Tanda tangan `(array $params, Request $request): Response`. Parameter request boleh dihilangkan bila tidak dipakai.
2. Daftarkan rute di `routes.php` sebelum catch-all `/api/{path:.*}`, dan sebelum rute resource bila path-nya bertabrakan.
3. Error terkontrol lewat `HttpException`, misalnya `throw new HttpException(422, 'VALIDATION_ERROR', 'Judul wajib diisi')`.

Query khusus (JOIN, LIKE, agregat) lewat `Database` dan prepared statement. Jangan sambung input pengguna ke string SQL. Bila query dipakai di lebih dari satu tempat, taruh di repository, bukan di controller. Controller tidak membuat `PDO`.

Auth bukan tugas controller. Pasang middleware pada rute: `AuthMiddleware`, lalu `RequireRole` bila perlu. Role yang ada hanya `user` dan `admin`. Token dikirim di header `Authorization: Bearer`, bukan di URL.

Tampilan:

- Halaman publik di `public/<nama>.html` atau folder `public/<cabang>/`. Nama cabang bebas. `/<cabang>` membaca `public/<cabang>.html`, atau `public/<cabang>/index.html` bila berkas datar itu tidak ada. `/<cabang>/<halaman>` membaca `public/<cabang>/<halaman>.html` atau `public/<cabang>/<halaman>/index.html`. `<base>` cabang mengikuti folder berkas. Aset statis cabang (css, js, gambar) dilayani dari folder yang sama.
- Gaya situs publik bebas. `public/assets/css/style.css` hanya contoh minimal. Jangan menambah aturan gaya atau mesin tema di `vendor/`.
- Dashboard di `public/dashboard/`. Subrute sama: `/dashboard/cabang` dan `/dashboard/cabang/halaman`. `<base>` tetap `/dashboard/` supaya shell dan tautan menu (`settings`) tidak bergeser. Path yang diawali `/` mengabaikan `<base>`.
- Sidebar dashboard: `aside-header` hanya ikon menu. Desktop (lebar 992px ke atas) menyembunyikan `aside-header`, tinggi `aside-body` `calc(100% - 0px)`, lipat sidebar lewat ikon di dalam `aside-loggedin`. Mobile menampilkan ikon menu di kanan (`justify-content: flex-end`) dan tinggi `aside-body` `calc(100% - 59px)`. Aturan ini ada di akhir `assets/css/dashforge.dashboard.css`.
- Skrip halaman dashboard mendaftarkan `window.nxPages` lalu memanggilnya di baris terakhir. Nama fungsi mengikuti nama berkas.
- Font di `public/dashboard/assets/fonts/` dipakai CSS. Jangan dihapus.

## Yang tidak dilakukan

- Menghapus fitur, font, atau file yang sudah ada tanpa permintaan.
- Mengganti pola yang sudah ada: JSON API, Model-driven CRUD, satu `index.php`.
- Menambah dependency bila yang ada sudah cukup. Dependensi Composer dicek di `composer.json`, dipasang ke `deps/`.
- Menaruh kata sandi, token, atau `DB_PASS` di kode PHP. Basis data dibaca dari lingkungan (`.env`).
- Mengubah API publik, tipe kolom yang sudah ada, atau peran di luar `user` dan `admin`.
- Refactor besar yang tidak diminta.
- Memakai subagent. Kerjakan seluruh tugas di agen utama.

## Urutan kerja

1. Pahami permintaan dan baca kode serta README folder yang terkait.
2. Untuk tugas besar atau satu modul: tulis rencana di `README.md` folder itu (masalah, keputusan, file yang disentuh) sebelum mengubah kode. Setelah selesai dan dicek, tandai `SUDAH DIEKSEKUSI` di README yang sama.
3. Tugas kecil yang sudah spesifik dikerjakan langsung.
4. Ubah hanya bagian yang diperlukan.
5. Cek hasilnya:
   - `php -l` pada file PHP yang diubah.
   - Dari akar proyek: `php vendor/bin/test.php`.
   - Perubahan tampilan dicek lewat halaman yang memakai file itu.

PHP di mesin ini: `C:\Nxserver\bin\php\php.exe` bila perintah `php` tidak ada di PATH. Basis data lokal kerangka: `127.0.0.1:3310`, nama `framework`.

## Git

Commit hanya bila diminta. Pesan commit menjelaskan alasan perubahan. Jangan `reset --hard`, `clean`, force push, atau menghapus branch sebelum pengguna menyetujuinya. Jangan amend kecuali diminta dan commit itu belum di-push.

## Bacaan

- `README.md` — kontrak API, auth, storage, keputusan yang sudah disepakati.
- `vendor/src/Model/README.md` — field, relasi, soft delete.
- `vendor/src/Controller/README.md` — bentuk controller.
- `vendor/src/Repository/README.md` — CRUD dan validasi.
- `vendor/src/Middleware/README.md` — auth, role, CSRF, rate limit.
- `vendor/config/README.md` — urutan rute dan env.
- `vendor/bin/README.md` — migrate, seed, purge, test.
- `cli/README.md` — pemasang `sekat`, terpisah dari mesin PHP.
