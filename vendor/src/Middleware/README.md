# Middleware

Middleware dijalankan **sebelum** controller, setelah rute cocok. Bisa menghentikan request (mengembalikan `Response`) atau meneruskan (mengembalikan `null`).

Kontraknya ada di `App\Core\Middleware`:

```php
interface Middleware
{
    public function handle(Request $request): ?Response;
}
```

- Balasan `Response` → request dihentikan, controller tidak dipanggil.
- Balasan `null` → lanjut ke middleware berikutnya lalu controller.
- Melempar `HttpException` juga sah dan berubah jadi JSON error.

## Cara memakai

Rute menerima daftar middleware sebagai argumen ketiga:

```php
$router->get('/api/me', [new MeController(), 'show'], [$auth]);
$router->post('/api/posts', [$controller, 'store'], [$csrf, $auth, $adminOnly]);
```

Urutan = urutan eksekusi. Contoh di atas: cek CSRF dulu, lalu auth user, lalu role admin.

## Daftar middleware

| Kelas | Tugas | Gagal bila |
| --- | --- | --- |
| `AuthMiddleware` | memvalidasi access token user | tidak ada/rusak token → `401 UNAUTHORIZED`; menempel `user` + `token` ke request |
| `RequireRole` | memastikan user punya role tertentu | role tidak cocok → `403 FORBIDDEN` |
| `RateLimitMiddleware` | membatasi request per IP+path | melewati batas → `429 TOO_MANY_REQUESTS` |
| `CsrfMiddleware` | perlindungan CSRF double-submit | cookie `XSRF-TOKEN` ≠ header `X-XSRF-TOKEN` → `403 TOKEN_MISMATCH` |
| `ApiTokenMiddleware` | menerima Bearer token API klien + menuntut grant | token invalid/kedaluwarsa → `401`; tanpa grant → `403` |

### AuthMiddleware

```php
$auth = new AuthMiddleware($tokens);
```

Setelah lolos, request membawa `user` (id, name, email, role) dan `token`:

```php
$user = $request->attribute('user');
```

### RequireRole

```php
new RequireRole('admin')
```

Harus dipasang **setelah** `AuthMiddleware`.

### RateLimitMiddleware

```php
new RateLimitMiddleware(new RateLimiter($dir), 10, 60); // 10 request / 60 detik
```

Dipasang pada login/register/refresh.

### CsrfMiddleware

```php
$csrf = new CsrfMiddleware();
```

Hanya aktif bila cookie `XSRF-TOKEN` ada; request dengan `Authorization: Bearer` dilewati.

### ApiTokenMiddleware

```php
new ApiTokenMiddleware($apiTokens, 'api', 'protected');
```

Menerima token API klien (bukan user) dan menuntut grant `api/protected`. Setelah lolos, request membawa `client` (nama token).

## Menulis middleware sendiri

1. Buat kelas mengimplementasikan `App\Core\Middleware`.
2. Isi `handle(Request $request): ?Response`.
3. Gunakan di rute.

```php
namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

final class WaktuKerjaMiddleware implements Middleware
{
    public function handle(Request $request): ?Response
    {
        if (date('H') < 8 || date('H') >= 17) {
            throw new HttpException(403, 'DI LUAR JAM KERJA', 'Di luar jam kerja');
        }

        return null;
    }
}
```

Daftarkan:

```php
$router->post('/api/posts', [$controller, 'store'], [new WaktuKerjaMiddleware(), $auth, $adminOnly]);
```

## Aturan

1. Middleware hanya boleh membaca/validasi request atau menghentikannya; logika bisnis ada di controller.
2. Jangan menaruh state antar request di objek middleware; objek dibuat ulang tiap rute.
3. Middleware yang butuh data (repo/limiter) dikonstruksi di `vendor/config/routes.php`.
4. Untuk atribut yang diteruskan ke controller, pakai `$request->setAttribute()`.`