# Bin

Script baris perintah. Dijalankan manual, bukan dari browser.

## Isi

| Script | Perintah | Tugas |
| --- | --- | --- |
| `migrate.php` | `php vendor/bin/migrate.php` | buat database, jalankan SQL migration, sinkronkan tabel dari Model |
| `seed.php` | `php vendor/bin/seed.php` | isi 2 post + user demo bila masih kosong |
| `purge.php` | `php vendor/bin/purge.php` | hapus token kedaluwarsa, log >14 hari, cache rate limit lama |
| `test.php` | `php vendor/bin/test.php` | 64 uji integrasi HTTP (API, auth, refresh, verifikasi & reset, CSRF, role, relasi, audit, soft delete, upload) |
| `router.php` | `php -S 127.0.0.1:8123 -t . vendor/bin/router.php` | router untuk dev server / CI (meniru `.htaccess`) |

Jalankan dari root project (`C:\Nxserver\www\framework`).

## migrate.php

Urutan kerja:

1. Membuat database bila belum ada.
2. Membuat tabel `migrations` (penanda file SQL yang sudah jalan).
3. Menjalankan setiap file di `vendor/database/migrations/*.sql` satu kali.
4. Memanggil `Schema::sync()` untuk setiap Model di `vendor/config/models.php`.

Aman dijalankan berulang: SQL yang sudah tercatat dilewati, `CREATE TABLE IF NOT EXISTS` tidak menghapus data.

## seed.php

```bash
php vendor/bin/seed.php
```

Hanya menambah data bila tabel kosong, sehingga tidak menduplikasi.

## Menambah perintah baru

1. Buat file di folder ini, misal `stats.php`.
2. Daftarkan autoloader bila memakai kelas `App\`:

```php
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = $root . '/vendor/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
```

3. Cetak hasil dengan `echo`; jangan mengirim header HTTP.

## Aturan

1. Script harus bisa dijalankan berulang tanpa merusak data.
2. Tidak boleh bergantung pada `$_SERVER` atau request browser.
3. Kesalahan database biarkan menjadi exception agar terlihat saat dijalankan.