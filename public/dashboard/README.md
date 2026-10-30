# DashForge Dashboard

## Tautan dan skrip halaman

Tautan di dalam dashboard memakai path relatif (`settings`, `../docs`). Path yang diawali `/` mengabaikan `<base>` dan jatuh ke akar host.

Contoh URL di HTML memakai `__APP_ORIGIN__`. Server menggantinya dengan alamat yang sedang dibuka, misalnya `http://localhost/framework` atau `http://framework.local`.

Skrip halaman mendaftarkan `window.nxPages` lalu memanggilnya di baris terakhir. Nama fungsi mengikuti nama berkas: `settings.js` menjadi `settings`, `model-free.js` menjadi `modelFree`.

## Catatan Pengembangan

### Mengubah Content atau Style Content-Body

Jika ingin mengubah **konten** atau **style content-body**, referensi dapat diambil dari:

```
D:\templates\theme\HTML
D:\templates\theme\daspage
D:\templates\theme\HTML\components
```

Folder tersebut berisi template HTML lengkap yang dapat digunakan sebagai referensi untuk:
- Layout content-body
- Styling komponen
- Struktur halaman
- Contoh implementasi

### Catatan Font

**Semua font di `assets/fonts/` digunakan oleh CSS** dan **TIDAK BOLEH dihapus**.

Font yang digunakan:
- **IBM Plex Sans** (dashforge.css)
- **Inter UI** (dashforge.dashboard.css)

---

## Struktur Folder

```
daspage/
├── assets/
│   ├── css/          # CSS internal (dashforge.css, dll)
│   ├── js/           # JavaScript internal
│   ├── lib/          # Library eksternal (jquery, bootstrap, dll)
│   ├── img/          # Gambar (favicon)
│   ├── fonts/        # Font files (JANGAN DIHAPUS!)
│   └── data/         # Data files
├── index.html        # Halaman utama
└── README.md         # Dokumentasi ini
```

## Library yang Digunakan

- **jQuery** - JavaScript library
- **Bootstrap 4** - CSS framework
- **Font Awesome** - Icon library
- **Chart.js** - Charting library
- **jQuery Flot** - Charting plugin
- **jQVMap** - Vector maps
- **Feather Icons** - Icon set
- **Perfect Scrollbar** - Custom scrollbar
- **js-cookie** - Cookie handling
