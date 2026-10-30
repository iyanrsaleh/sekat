<?php

declare(strict_types=1);

/**
 * Uji integrasi HTTP.
 *
 *   php vendor/bin/test.php
 *
 * Base URL bisa diganti:
 *   TEST_BASE=http://localhost/framework php vendor/bin/test.php
 *
 * Butuh server Apache + database yang sudah dimigrasi.
 */

$base = rtrim(getenv('TEST_BASE') ?: 'http://localhost/framework', '/');

$passed = 0;
$failed = 0;
$token = null;
$createdId = null;

/**
 * @return array{status: int, json: array<string, mixed>|null, body: string}
 */
function call(string $method, string $path, ?array $payload = null, ?string $token = null, array $extraHeaders = [], string $cookie = ''): array
{
    global $base;

    $handle = curl_init($base . $path);
    $headers = array_merge(['Accept: application/json'], $extraHeaders);

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10,
    ]);

    if ($cookie !== '') {
        curl_setopt($handle, CURLOPT_COOKIE, $cookie);
    }

    $body = (string) curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    $json = json_decode($body, true);

    return ['status' => $status, 'json' => is_array($json) ? $json : null, 'body' => $body];
}


/**
 * @return array{status: int, json: array<string, mixed>|null}
 */
function upload(string $path, ?string $token): array
{
    global $base;

    $file = tempnam(sys_get_temp_dir(), 'nxc') . '.png';
    file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

    $handle = curl_init($base . $path);
    $headers = ['Accept: application/json'];

    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => ['file' => new CURLFile($file, 'image/png', 'pixel.png')],
        CURLOPT_TIMEOUT => 10,
    ]);

    $body = (string) curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    @unlink($file);

    $json = json_decode($body, true);

    return ['status' => $status, 'json' => is_array($json) ? $json : null];
}
function check(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "PASS  {$name}\n";
        return;
    }

    $failed++;
    echo "FAIL  {$name}" . ($detail === '' ? '' : " — {$detail}") . "\n";
}

$response = call('GET', '/api/ping');
check('GET /api/ping', $response['status'] === 200 && ($response['json']['ok'] ?? false) === true);

$response = call('GET', '/api/posts');
check('GET /api/posts', $response['status'] === 200 && isset($response['json']['data']['items']));

$response = call('GET', '/api/posts?page=1&per_page=1&sort=id&order=asc');
check(
    'pagination',
    $response['status'] === 200
        && ($response['json']['data']['meta']['per_page'] ?? null) === 1
        && count($response['json']['data']['items']) <= 1,
);

$response = call('POST', '/api/posts', ['title' => 'tanpa token', 'body' => 'x']);
check('POST tanpa token -> 401', $response['status'] === 401);

$response = call('POST', '/api/auth/login', ['email' => 'admin@example.com', 'password' => 'admin123']);
$token = is_array($response['json']) ? ($response['json']['data']['access_token'] ?? null) : null;
$refreshToken = is_array($response['json']) ? ($response['json']['data']['refresh_token'] ?? null) : null;
check('login admin', $response['status'] === 200 && is_string($token));

$response = call('GET', '/api/me', null, $token);
check('GET /api/me', $response['status'] === 200 && ($response['json']['data']['role'] ?? '') === 'admin');

$previousRefresh = $refreshToken;
$response = call('POST', '/api/auth/refresh', ['refresh_token' => $refreshToken]);
$newAccess = is_array($response['json']) ? ($response['json']['data']['access_token'] ?? null) : null;
$newRefresh = is_array($response['json']) ? ($response['json']['data']['refresh_token'] ?? null) : null;
check('refresh token -> 200', $response['status'] === 200 && is_string($newAccess));

if (is_string($newAccess) && is_string($newRefresh)) {
    $token = $newAccess;
    $refreshToken = $newRefresh;
    check('akses token baru -> me 200', call('GET', '/api/me', null, $token)['status'] === 200);
    check('refresh token lama dipakai lagi -> 401', call('POST', '/api/auth/refresh', ['refresh_token' => $previousRefresh])['status'] === 401);
} else {
    check('refresh token baru valid', false);
}

$response = call('POST', '/api/posts', ['title' => 'Uji integrasi', 'body' => 'Dibuat oleh test', 'user_id' => 1], $token);
$createdId = is_array($response['json']) ? ($response['json']['data']['id'] ?? null) : null;
check('POST dengan admin -> 201', $response['status'] === 201 && is_int($createdId));

if (is_int($createdId)) {
    $response = call('GET', '/api/posts/' . $createdId . '?with=user');
    check(
        'relasi ?with=user',
        $response['status'] === 200 && ($response['json']['data']['user']['email'] ?? '') === 'admin@example.com',
    );

    $response = call('PATCH', '/api/posts/' . $createdId, ['title' => 'Uji integrasi (patch)'], $token);
    check('PATCH update', $response['status'] === 200 && ($response['json']['data']['title'] ?? '') === 'Uji integrasi (patch)');

    $response = call('DELETE', '/api/posts/' . $createdId, null, $token);
    check('DELETE (soft) -> 204', $response['status'] === 204);

    $response = call('GET', '/api/posts/' . $createdId);
    check('post terhapus -> 404', $response['status'] === 404);

    $response = call('POST', '/api/posts/' . $createdId . '/restore', [], $token);
    check('restore -> 200', $response['status'] === 200);

    $response = call('GET', '/api/posts/' . $createdId);
    check('post setelah restore -> 200', $response['status'] === 200);

    $response = call('DELETE', '/api/posts/' . $createdId . '?force=true', null, $token);
    check('force delete -> 204', $response['status'] === 204);
}

$response = call('POST', '/api/posts', ['title' => 'FK salah', 'body' => 'x', 'user_id' => 999999], $token);
check('relasi tidak ada -> 422', $response['status'] === 422 && ($response['json']['error']['code'] ?? '') === 'INVALID_RELATION');

$response = call('POST', '/api/tidakada', []);
check('POST endpoint asing -> 404', $response['status'] === 404 && ($response['json']['error']['code'] ?? '') === 'NOT_FOUND');

$response = call('GET', '/api/tidakada');
check('GET endpoint asing -> 404', $response['status'] === 404);

$response = call('POST', '/api/auth/login', ['email' => 'admin@example.com', 'password' => 'salah']);
check('login password salah -> 401', $response['status'] === 401 && ($response['json']['error']['code'] ?? '') === 'INVALID_CREDENTIALS');

$response = call('GET', '/api/audit-logs');
check('audit tanpa token -> 401', $response['status'] === 401);

$response = call('GET', '/api/audit-logs?per_page=5', null, $token);
check('audit admin -> 200', $response['status'] === 200 && isset($response['json']['data']['items']));

$response = call('GET', '/api/stats', null, $token);
check('stats admin -> 200', $response['status'] === 200 && isset($response['json']['data']['counts']['posts']));

$response = call('GET', '/api/stats');
check('stats tanpa token -> 401', $response['status'] === 401);

$response = upload('/api/uploads', null);
check('upload tanpa token -> 401', $response['status'] === 401);

$response = upload('/api/uploads', $token);
$uploadPath = is_array($response['json']) ? ($response['json']['data']['url'] ?? null) : null;
check('upload admin -> 201', $response['status'] === 201 && is_string($uploadPath));

if (is_string($uploadPath)) {
    $response = call('GET', '/' . $uploadPath);
    check('ambil file upload (proxy) -> 200', $response['status'] === 200);

    $response = call('GET', '/api/uploads', null, $token);
    check('daftar upload -> 200', $response['status'] === 200 && count($response['json']['data']['items'] ?? []) >= 1);

    $response = call('DELETE', '/' . $uploadPath, null, $token);
    check('hapus upload -> 204', $response['status'] === 204);
}

$rand = substr(bin2hex(random_bytes(3)), 0, 6);
$regEmail = 't' . $rand . '@example.com';
$response = call('POST', '/api/auth/register', ['name' => 'Verifikasi', 'email' => $regEmail, 'password' => 'rahasia123', 'role' => 'admin']);
$verifyToken = is_array($response['json']) ? ($response['json']['data']['verify_token'] ?? null) : null;
$registered = is_array($response['json']) ? ($response['json']['data']['user'] ?? []) : [];
check('register -> verify_token', $response['status'] === 201 && is_string($verifyToken));
check('register tetap user', ($registered['role'] ?? '') === 'user');

if (is_string($verifyToken)) {
    $response = call('POST', '/api/auth/verify-email', ['token' => $verifyToken]);
    check('verify email -> 200', $response['status'] === 200 && ($response['json']['data']['verified'] ?? false) === true);

    $response = call('POST', '/api/auth/verify-email', ['token' => $verifyToken]);
    check('verify email ulang -> 400', $response['status'] === 400);
}

$response = call('POST', '/api/auth/forgot-password', ['email' => 'admin@example.com']);
$resetToken = is_array($response['json']) ? ($response['json']['data']['reset_token'] ?? null) : null;
check('forgot password -> reset_token', $response['status'] === 200 && is_string($resetToken));

if (is_string($resetToken)) {
    $response = call('POST', '/api/auth/reset-password', ['token' => $resetToken, 'password' => 'ganti12345']);
    check('reset password -> 200', $response['status'] === 200);

    $response = call('POST', '/api/auth/login', ['email' => 'admin@example.com', 'password' => 'ganti12345']);
    check('login password baru -> 200', $response['status'] === 200);

    // pulihkan password admin untuk pengujian berikutnya
    $response = call('POST', '/api/auth/forgot-password', ['email' => 'admin@example.com']);
    $restoreToken = is_array($response['json']) ? ($response['json']['data']['reset_token'] ?? null) : null;
    check('pulihkan password admin', is_string($restoreToken));
    if (is_string($restoreToken)) {
        call('POST', '/api/auth/reset-password', ['token' => $restoreToken, 'password' => 'admin123']);
    }
}
$response = call('GET', '/api/csrf');
check('GET /api/csrf', $response['status'] === 200 && is_string($response['json']['data']['token'] ?? null));

$response = call('POST', '/api/posts', ['title' => 'csrf', 'body' => 'x'], null, ['X-XSRF-TOKEN: salah'], 'XSRF-TOKEN=token_xyz');
check('CSRF token tidak cocok -> 403', $response['status'] === 403 && ($response['json']['error']['code'] ?? '') === 'TOKEN_MISMATCH');
// Token API (nama + masa berlaku + grant)
$response = call('POST', '/api/api-tokens', ['name' => 'uji_klien', 'days' => 1], $token);
$apiClient = is_array($response['json']) ? ($response['json']['data']['token'] ?? null) : null;
check('buat token API -> 201', $response['status'] === 201 && is_string($apiClient) && str_starts_with($apiClient, 'NX_'));
check('token tidak terlalu panjang', is_string($apiClient) && strlen($apiClient) <= 32);

$response = call('GET', '/api/protected', null, $apiClient);
check('resource tanpa grant -> 403', $response['status'] === 403);

$response = call('POST', '/api/api-grants', ['token' => 'uji_klien', 'kind' => 'api', 'name' => 'protected'], $token);
check('beri grant -> 201', $response['status'] === 201);

$response = call('GET', '/api/protected', null, $apiClient);
check('resource dengan grant -> 200', $response['status'] === 200);

$response = call('GET', '/api/api-tokens', null, $token);
check('daftar token -> 200', $response['status'] === 200 && count($response['json']['data']['items'] ?? []) >= 1);

$response = call('POST', '/api/api-tokens', ['name' => 'uji_dua', 'days' => 1, 'months' => 1], $token);
check('dua field masa berlaku -> 400', $response['status'] === 400);

$response = call('DELETE', '/api/api-tokens/uji_klien', null, $token);
check('cabut token -> 204', $response['status'] === 204);

$response = call('GET', '/api/protected', null, $apiClient);
check('token dicabut -> 401', $response['status'] === 401);
// Proxy
$response = call('GET', '/api/self');
check('proxy /api/self -> 200', $response['status'] === 200 && ($response['json']['ok'] ?? false) === true);

$response = call('GET', '/api/tidakada/x');
check('proxy alias asing -> 404', $response['status'] === 404 && ($response['json']['error']['code'] ?? '') === 'NOT_FOUND');

$response = call('GET', '/api/feed/x');
check('proxy tujuan tidak ada -> 502', $response['status'] === 502 && ($response['json']['error'] ?? '') === 'bad_gateway');

$response = call('OPTIONS', '/api/self');
check('proxy OPTIONS -> 204', $response['status'] === 204);
// Pengaturan dasar
$response = call('GET', '/api/settings/public');
check('settings publik -> 200', $response['status'] === 200 && isset($response['json']['data']['app_name']));

$response = call('GET', '/api/settings');
check('settings tanpa token -> 401', $response['status'] === 401);

$response = call('PUT', '/api/settings', ['site_title' => 'Uji Judul'], $token);
check('settings simpan -> 200', $response['status'] === 200 && ($response['json']['data']['site_title'] ?? '') === 'Uji Judul');

$response = call('PUT', '/api/settings', ['password' => 'x'], $token);
check('settings kunci invalid -> 422', $response['status'] === 422 && ($response['json']['error']['code'] ?? '') === 'UNKNOWN_SETTING');

call('PUT', '/api/settings', ['site_title' => 'Sekat'], $token);
// Profil: avatar & ubah password
$response = upload('/api/me/avatar', $token);
check('upload avatar -> 200', $response['status'] === 200 && is_string($response['json']['data']['avatar'] ?? null));

$response = call('PUT', '/api/me/password', ['current_password' => 'salah', 'new_password' => 'baru12345'], $token);
check('password lama salah -> 422', $response['status'] === 422 && ($response['json']['error']['code'] ?? '') === 'INVALID_PASSWORD');

$response = call('PUT', '/api/me/password', ['current_password' => 'admin123', 'new_password' => 'baru12345'], $token);
check('ubah password -> 200', $response['status'] === 200);

$response = call('POST', '/api/auth/login', ['email' => 'admin@example.com', 'password' => 'baru12345']);
$freshToken = is_array($response['json']) ? ($response['json']['data']['access_token'] ?? null) : null;
check('login dengan password baru -> 200', $response['status'] === 200 && is_string($freshToken));

if (is_string($freshToken)) {
    call('PUT', '/api/me/password', ['current_password' => 'baru12345', 'new_password' => 'admin123'], $freshToken);
}
// Profil: edit nama/email + logout semua perangkat
$response = call('PUT', '/api/me', ['name' => 'Admin Uji', 'email' => 'admin@example.com'], $token);
check('edit profil -> 200', $response['status'] === 200 && ($response['json']['data']['name'] ?? '') === 'Admin Uji');
call('PUT', '/api/me', ['name' => 'Admin', 'email' => 'admin@example.com'], $token);

$response = call('PUT', '/api/me', ['name' => 'Admin', 'email' => 'bukan-email'], $token);
check('email invalid -> 422', $response['status'] === 422);

$response = call('POST', '/api/me/logout-all', [], $token);
check('logout semua -> 204', $response['status'] === 204);

$response = call('GET', '/api/me', null, $token);
check('token dicabut -> 401', $response['status'] === 401);

call('POST', '/api/auth/login', ['email' => 'admin@example.com', 'password' => 'admin123']);
echo "\nHasil: {$passed} lulus, {$failed} gagal\n";

exit($failed === 0 ? 0 : 1);