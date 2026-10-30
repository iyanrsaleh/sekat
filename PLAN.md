# Rencana: pemasang Sekat

Rilis kerangka sudah ada di [v1.0.0](https://github.com/iyanrsaleh/sekat/releases/tag/v1.0.0). Yang belum ada adalah program pemasang. Program itu ditulis dengan C++, satu sumber untuk Windows dan Linux, dan perintahnya bernama `sekat`.

Kode pemasang ada di `cli/`.

## Dua lapis

| Lapis | Bentuk | Tugas |
| --- | --- | --- |
| Pemasang | `sekat.exe` di Windows, `sekat` di Linux | Mengunduh rilis, menaruh berkas, memanggil PHP |
| Kerangka | PHP 8.1 di folder proyek | Tetap seperti sekarang: `public/`, `vendor/`, satu `index.php` |

Pemasang tidak berisi mesin web. `migrate`, `seed`, dan server pengembangan tetap skrip PHP yang sudah ada. `sekat` hanya memastikan PHP terpasang, lalu menjalankan skrip itu.

## Memasang perintah ke komputer

Orang mengunduh satu berkas dari rilis GitHub, lalu dari folder unduhan menjalankan:

```text
sekat install --global
```

Di Windows berkas unduhan bernama `sekat.exe`. Perintah yang sama:

```text
sekat.exe install --global
```

Yang dikerjakan:

1. Menyalin program ke tempat tetap milik pengguna.
   - Windows: `%LOCALAPPDATA%\Sekat\bin\sekat.exe`
   - Linux: `~/.local/bin/sekat`
2. Menambahkan folder itu ke PATH pengguna bila belum ada.
   - Windows: PATH pada lingkungan pengguna, lewat registri `Environment`.
   - Linux: baris di `~/.profile` bila `~/.local/bin` belum masuk PATH.
3. Mencatat versi yang terpasang di `%LOCALAPPDATA%\Sekat\version` atau `~/.local/share/sekat/version`.

Setelah jendela terminal dibuka lagi, `sekat` bisa dipanggil dari folder mana pun. Tidak memakai hak administrator. Tidak menulis ke `C:\Windows` atau `/usr`.

## Membuat proyek

```text
cd D:\path\ke\proyek
sekat install
```

Folder saat ini harus kosong. `sekat` mengunduh arsip rilis `v1.0.0` dari GitHub, membuka isinya langsung di folder itu, dan tidak membungkusnya dalam folder `sekat-1.0.0`.

Yang ikut:

- `index.php`, `.htaccess`, `composer.json`, `README.md`
- `public/`
- `vendor/` kode PHP, konfigurasi, migrasi, dan skrip `vendor/bin`
- `deps/.htaccess`

Yang tidak ikut dari mesin pengembang: log, cache, unggahan, surat, `.env`, dan kata sandi. Sumber unduhan adalah arsip rilis GitHub, bukan salinan folder kerja.

Bila folder tidak kosong, perintah berhenti dan memberitahu isi yang sudah ada. `--force` hanya dipakai bila pengguna memang ingin menimpa.

Versi rilis bisa dipilih:

```text
sekat install --version 1.0.0
```

Tanpa `--version`, yang diunduh adalah rilis stabil terakhir.

## Perintah lain

| Perintah | Tugas |
| --- | --- |
| `sekat version` | Menulis versi pemasang dan, bila dijalankan di dalam proyek, versi kerangka di folder itu |
| `sekat help` | Daftar perintah |
| `sekat check` | Memeriksa `php` di PATH dan versi PHP sekurang-kurangnya 8.1 |
| `sekat db` | Menanyakan host, port, nama basis data, pengguna, dan kata sandi, lalu menulis `.env` proyek. Nilai bawaan lokal: `127.0.0.1`, port `3310`, basis data sesuai nama folder |
| `sekat migrate` | Menjalankan `php vendor/bin/migrate.php` dari akar proyek |
| `sekat seed` | Menjalankan `php vendor/bin/seed.php` |
| `sekat serve` | Menjalankan `php -S 127.0.0.1:8123 -t . vendor/bin/router.php` |
| `sekat purge` | Menjalankan `php vendor/bin/purge.php` |
| `sekat test` | Menjalankan `php vendor/bin/test.php` |
| `sekat update` | Memperbarui program `sekat` yang terpasang global ke rilis stabil terakhir |
| `sekat update --project` | Memperbarui berkas kerangka di folder ini ke rilis stabil terakhir, tanpa menghapus `.env` dan `vendor/storage` |

`sekat db` perlu kerangka membaca `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASS` dari lingkungan. Saat kode pemasang ditulis, `vendor/config/database.php` diselaraskan supaya kata sandi proyek datang dari `.env`, bukan dari nilai tetap di berkas PHP.

Perintah yang memanggil PHP mencari akar proyek dengan berjalan naik sampai menemukan `index.php` dan `vendor/public/index.php`. Bila tidak ketemu, perintah berhenti dengan kalimat bahwa folder ini belum proyek Sekat.

## Bentuk program C++

Satu proyek CMake, standar C++17.

```text
cli/
├── CMakeLists.txt
├── src/
│   ├── main.cpp          # memilah perintah
│   ├── install.cpp       # --global dan salin proyek
│   ├── http.cpp          # unduh rilis GitHub
│   ├── path.cpp          # PATH Windows dan Linux
│   └── php.cpp           # cari php dan jalankan skrip
└── README.md
```

Yang dipakai dari sistem, tanpa pustaka besar:

- Windows: WinHTTP untuk unduh, API registri untuk PATH pengguna.
- Linux: unduh lewat HTTPS yang tersedia di sistem, sunting `~/.profile` untuk PATH.

Rilis GitHub `sekat` nanti memuat tiga aset:

| Aset | Isi |
| --- | --- |
| `sekat-1.0.0.zip` | Kerangka PHP untuk `sekat install` |
| `sekat-windows-x64.zip` | `sekat.exe` |
| `sekat-linux-x64.tar.gz` | biner `sekat` |

## Urutan saat dikerjakan

1. Selaraskan `database.php` dengan `.env`.
2. Bangun `sekat version` dan `sekat help`.
3. Bangun `sekat install --global` untuk Windows, lalu Linux.
4. Bangun `sekat install` yang mengunduh arsip rilis ke folder saat ini.
5. Sambungkan `check`, `db`, `migrate`, `seed`, `serve`, `purge`, `test`, dan `update`.
6. Unggah ketiga aset ke rilis GitHub.
