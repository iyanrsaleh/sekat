# Repository

Lapisan yang bicara ke database. Controller tidak pernah menyentuh PDO langsung.

## Isi

| File | Kelas | Tugas |
| --- | --- | --- |
| `CrudRepository.php` | `App\Repository\CrudRepository` | CRUD generik untuk semua Model |

## Kenapa hanya satu repository

`CrudRepository` membaca metadata Model (`$table`, `$fields`), sehingga tidak perlu repository per fitur. Menambah Model otomatis memakai repository yang sama.

```php
/** @param class-string<\App\Core\Model> $model */
$repository = new CrudRepository($database, App\Model\Post::class);
```

## Method

| Method | Hasil |
| --- | --- |
| `all()` | daftar semua baris (terbaru dulu), sudah diserialisasi |
| `paginate($page, $perPage, $filters, $sort, $order)` | daftar + `meta` (page, per_page, total, pages) |
| `find(int $id, array $with = [])` | satu baris atau `null`; `$with` menambahkan relasi |
| `create(array $input)` | validasi → insert → baris baru |
| `update(int $id, array $input)` | validasi → update → baris terbaru / `null` |
| `delete(int $id, bool $force = false)` | soft delete bila Model mendukung, atau hapus fisik |
| `restore(int $id)` | kembalikan baris soft delete |

## Validasi

Validasi dijalankan dari metadata Model sebelum menyentuh database:

| Aturan | Efek |
| --- | --- |
| `required` | `422 VALIDATION_ERROR` bila kosong saat create |
| `max` | batas panjang untuk `string` |
| `type` | cast ke `int`, `bool`, `float`, atau `string` |
| `label` | dipakai pada pesan error |
| `unique` | dicek ke database sebelum simpan; bentrok → `422 DUPLICATE_VALUE` |

Field yang tidak ada di `$fields` diabaikan, sehingga input asing tidak bisa masuk tabel.

## Relasi
`paginate()` dan `find()` menerima daftar relasi (dari `?with=`). Relasi `belongsTo` mengambil satu baris, `hasMany` mengambil banyak baris; bentuk JSON mengikuti `serialize()` Model terkait.

## Query kustom

Bila butuh JOIN/agregat, buat repository sendiri di folder ini dan pakai `App\Core\Database`:

```php
final class PostReportRepository
{
    public function __construct(private readonly Database $database) {}

    public function perMonth(): array
    {
        return $this->database->select(
            'SELECT DATE_FORMAT(created_at, "%Y-%m") AS bulan, COUNT(*) AS total
             FROM posts GROUP BY bulan ORDER BY bulan DESC'
        );
    }
}
```

## Aturan

1. Satu-satunya lapisan yang boleh menyusun SQL.
2. Selalu pakai parameter ter-bind; jangan menyambung input user.
3. Kembalikan array siap-JSON, bukan `PDOStatement`.
4. Jangan menaruh logika HTTP (status code, header) di sini.