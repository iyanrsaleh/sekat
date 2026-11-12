# Operasi Sekat di server

Checklist singkat setelah kode siap. Bukan panduan panel hosting.

## Produksi

- `APP_ENV=production` di `.env` server (bukan salinan dari mesin lokal).
- Jangan unggah `.env` lokal. Buat baru di server: DB, `TRUSTED_PROXIES`, `SEED_ADMIN_PASSWORD` (≥12 karakter).
- Document root = folder proyek (bukan hanya `public/`). Tolak unduhan `vendor/`, `deps/`, `.env` (lihat `nginx.conf` atau `.htaccess`).
- PHP ≥ 8.1. Beberapa worker PHP-FPM / OpenLiteSpeed = multi-instance ringan di satu mesin.

## Cadangan

- Basis data (mysqldump / panel) secara berkala.
- Folder `vendor/storage/uploads`.
- Salinan `.env` server disimpan offline dengan akses terbatas (bukan di repo).

## Pantau

- `GET /api/health` — peantau uptime (HTTP 200 sehat, 503 bermasalah). Tanpa auth.
- `GET /api/ping` — cek hidup cepat.
- Log aplikasi: `vendor/storage/logs/`.
- Bersihkan berkala: `php vendor/bin/purge.php`.

## Aset dan CDN

- `nginx.conf` memberi `Cache-Control` panjang untuk css/js/gambar/font.
- HTML dan `/api/*` tidak di-cache agresif.
- CDN nanti: arahkan `theme/assets` dan `assets` ke origin yang sama; jangan cache respons API.

## Antrian

- Job file di `vendor/storage/queue/` (pending / working / failed).
- Cron tiap menit dari akar proyek:

```bash
php vendor/bin/queue.php
```

- Handler awal: `mail.send` (`to`, `subject`, `body`). Kode boleh `Queue::push('mail.send', [...])` tanpa memaksa semua email lewat antrian.

## Cache file

- Cache aplikasi di `vendor/storage/cache/` (sudah `LOCK_EX`).
- Aman untuk beberapa proses PHP pada **disk lokal bersama**.
- Jangan taruh storage di NFS/shared network tanpa kunci terpusat (Redis, dll.) — rename/job bisa bentrok.
