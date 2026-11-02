# Aturan tema — SUDAH DIEKSEKUSI

Folder ini adalah kulit situs publik. Pengembang tampilan bekerja di sini. Folder aplikasi di `public/` (`dashboard/`) tetap di tempatnya.

Tidak ada PHP di folder ini. Skrip yang memanggil API tetap di `public/assets/js/` dan tidak dipindah ke tema.

## Berkas baku

| Berkas | Isi |
| --- | --- |
| `header.html` | Menu situs |
| `footer.html` | Kaki situs |
| `index.html`, `about.html`, `posts.html`, … | Halaman contoh |
| `assets/css/style.css` | Contoh gaya minimal |

CSS tema: `theme/assets/css/style.css`. Skrip API: `assets/js/...` di luar tema.
