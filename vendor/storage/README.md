# Storage

Berkas yang ditulis aplikasi saat berjalan. Bukan kode, bukan konfigurasi.

## Isi

| Folder | Isi | Ditulis oleh |
| --- | --- | --- |
| `logs/` | `app-YYYY-MM-DD.log` berisi exception | `App\Core\ErrorHandler` |
| `mail/` | pesan email (`*.eml`) dari `Mailer` | `App\Core\Mailer` |
| `cache/` | cache sementara (belum dipakai) | — |
| `uploads/` | gambar hasil `POST /api/uploads`, disimpan per tahun/bulan | `App\Controller\UploadController` |

## Log

Setiap exception yang tidak tertangani ditulis satu baris:

```text
[2026-10-28 09:18:52] App\Core\HttpException: Judul wajib diisi in .../CrudRepository.php:118
```

File dibuat otomatis per tanggal. Folder dibuat otomatis bila belum ada.

Contoh membaca 20 baris terakhir (PowerShell):

```powershell
Get-Content vendor\storage\logs\*.log -Tail 20
```

## Aturan

1. Jangan menyimpan kode atau konfigurasi di folder ini.
2. Jangan mengirim file log ke browser.
3. Di produksi, batasi akses folder ini dan bersihkan log lama secara berkala.
4. Nama file upload harus dibuat ulang oleh server (jangan pakai nama asli user).
5. Jangan mengandalkan `uploads/` untuk data penting; simpan path-nya di database.

## Akses web

`storage/` berada di dalam document root lokal. `.htaccess` hanya meneruskan URL yang bukan file fisik ke `index.php`, sehingga file log tetap bisa diakses lewat URL langsung bila nama file diketahui.

Untuk hosting, letakkan `storage/` di luar public root, atau tambahkan aturan penolakan di server.