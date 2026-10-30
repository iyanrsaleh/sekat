# Database

Berkas yang berhubungan dengan skema database.

## Isi

| Path | Isi |
| --- | --- |
| `migrations/` | file `.sql` yang dijalankan sekali oleh `vendor/bin/migrate.php` |
| `migrations/001_create_posts.sql` | tabel `posts` (contoh, dipakai project ini) |

## Cara kerja

1. `php vendor/bin/migrate.php` membuat database bila belum ada.
2. Tabel `migrations` mencatat nama file yang sudah dijalankan.
3. Setiap file `.sql` yang belum tercatat dijalankan, lalu dicatat.
4. Setelah itu, tabel setiap Model di `vendor/config/models.php` disinkronkan oleh `App\Core\Schema`.

## Kapan memakai SQL migration

| Kebutuhan | Cara |
| --- | --- |
| Tabel baru dari Model | cukup daftarkan Model, jalankan `migrate.php` |
| Mengubah tabel yang sudah ada (tambah/ubah kolom) | tulis file SQL baru, mis. `002_add_status_to_posts.sql` |
| Menghapus kolom, mengganti tipe, memindahkan data | file SQL baru; `Schema::sync()` tidak mengubah tabel lama |
| Index atau relasi khusus | file SQL baru |

## Format nama file

```text
001_create_posts.sql
002_add_status_to_posts.sql
003_create_comments.sql
```

Nomor urut di depan menentukan urutan eksekusi (diurutkan alfabetis). Jangan mengubah file yang sudah pernah dijalankan — buat file baru.

## Contoh migration

```sql
ALTER TABLE posts
    ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'draft' AFTER body;

CREATE INDEX idx_posts_status ON posts (status);
```

Setelah itu tambahkan `status` ke `$fields` pada `App\Model\Post` agar ikut tervalidasi dan terkirim ke API.

## Aturan

1. Satu file = satu perubahan logis.
2. Selalu bisa dijalankan pada database kosong (untuk instalasi baru).
3. Jangan memakai `DROP TABLE` tanpa alasan yang jelas.
4. Data contoh jangan dimasukkan ke migration; gunakan `vendor/bin/seed.php`.