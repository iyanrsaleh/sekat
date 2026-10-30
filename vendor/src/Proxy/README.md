# Proxy

Meneruskan permintaan HTTP ke mesin lain. Pihak luar hanya melihat alamat di server ini (`/api/<alias>`) — URL tujuan tidak pernah diekspos.

## Isi

| File | Tugas |
| --- | --- |
| `ProxyClient.php` | meneruskan request (curl, atau soket IPv4 bila curl tidak ada); header pemanggil dikirim apa adanya; redirect tidak dibuka |
| `README.md` | dokumentasi |

Config ada di `vendor/config/`:

| File | Isi |
| --- | --- |
| `proxy.php` | `['aliases' => ['feed', 'self']]` — daftar alias yang boleh dipanggil |
| `upstreams.php` | `['feed' => 'https://mesin-lain.example', 'self' => 'http://framework.local/api/ping']` — URL tujuan |

## Cara kerja

`/api/<alias>/<path>` dipetakan ke `<tujuan>/<path>`, query string ikut:

```text
GET /api/feed/posts/1  →  GET https://mesin-lain.example/posts/1
```

- Metode `GET/POST/PUT/PATCH/DELETE/HEAD` diteruskan beserta badan & `Content-Type`.
- `OPTIONS` dijawab lokal (204), tidak diteruskan.
- Rute proxy publik membuang `Authorization` sebelum meneruskan, supaya token di server ini tidak ikut ke tujuan.
- `ProxyClient::postJson()` mengirim header yang diberikan pemanggil, termasuk `Authorization`, untuk panggilan yang memang membutuhkan kunci ke host lain.
- Bila ekstensi curl tidak ada, sambungan memakai IPv4 dari `gethostbyname`. SNI dan header `Host` tetap nama asli. Batas waktu diatur pemanggil (`forward` 15 detik, `postJson` 20 detik).
- Segmen `..` ditolak.
- Alias yang tidak terdaftar tidak diklaim — `/api/posts` tetap milik resource API.

Daftar alias dibaca setiap request. Variabel `NX_PROXY_UPSTREAMS` (JSON objek alias => URL) menimpa `upstreams.php`.

## Balasan

| Status | `error` | Arti |
| --- | --- | --- |
| status tujuan | — | badan dari mesin tujuan diteruskan |
| `204` | — | jawaban `OPTIONS` |
| `400` | `bad_request` | path tidak sah |
| `404` | `not_found` | alias tidak ada (bila route proxy aktif) |
| `405` | `method_not_allowed` | metode di luar tabel |
| `502` | `bad_gateway` | mesin tujuan tidak menjawab |
| `503` | `driver_unavailable` | alias belum punya tujuan / tujuan tidak sah |

Pesan gagal tidak memuat URL tujuan.

## Menambah alias

1. Tambahkan nama di `vendor/config/proxy.php` `aliases`.
2. Tambahkan URL di `vendor/config/upstreams.php`.
3. Tidak perlu ubah rute — `vendor/config/routes.php` membuat route per alias.

Contoh `'self'` menunjuk ke server ini (`/api/ping`) untuk pengujian forwarding:

```text
GET /api/self          → 200 {"ok":true,...}  (diteruskan ke /api/ping)
GET /api/self/x        → 404 dari tujuan (diteruskan)
GET /api/feed/x        → 502 (host contoh tidak ada)
```

## Keamanan

- Jangan pernah menulis kredensial tujuan di `upstreams.php`.
- Rute proxy publik membuang `Authorization`, jadi token pengunjung tidak bocor ke tujuan.
- Untuk mengunci alias tertentu dengan token, gabungkan dengan `ApiTokenMiddleware` / `AuthMiddleware` pada route alias (lihat `vendor/config/routes.php`).