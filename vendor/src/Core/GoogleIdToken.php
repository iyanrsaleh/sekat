<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Memeriksa ID token Google (RS256) terhadap client ID aplikasi.
 */
final class GoogleIdToken
{
    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    /**
     * @return array{email: string, name: string}
     */
    public static function verify(string $jwt, string $clientId): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new HttpException(401, 'INVALID_GOOGLE_TOKEN', 'Token Google tidak valid');
        }

        [$headerPart, $payloadPart, $signaturePart] = $parts;
        $header = json_decode(self::base64UrlDecode($headerPart), true);
        $payload = json_decode(self::base64UrlDecode($payloadPart), true);

        if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'RS256') {
            throw new HttpException(401, 'INVALID_GOOGLE_TOKEN', 'Token Google tidak valid');
        }

        $kid = (string) ($header['kid'] ?? '');
        $pem = self::certificates()[$kid] ?? null;

        if (!is_string($pem) || $pem === '') {
            throw new HttpException(401, 'INVALID_GOOGLE_TOKEN', 'Token Google tidak valid');
        }

        $signature = self::base64UrlDecode($signaturePart);
        $verified = openssl_verify($headerPart . '.' . $payloadPart, $signature, $pem, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            throw new HttpException(401, 'INVALID_GOOGLE_TOKEN', 'Token Google tidak valid');
        }

        $issuer = (string) ($payload['iss'] ?? '');

        if ($issuer !== 'accounts.google.com' && $issuer !== 'https://accounts.google.com') {
            throw new HttpException(401, 'INVALID_GOOGLE_TOKEN', 'Token Google tidak valid');
        }

        $audience = $payload['aud'] ?? '';
        $audiences = is_array($audience) ? $audience : [$audience];

        if (!in_array($clientId, $audiences, true)) {
            throw new HttpException(401, 'INVALID_GOOGLE_TOKEN', 'Token Google tidak untuk aplikasi ini');
        }

        $expires = (int) ($payload['exp'] ?? 0);

        if ($expires < time()) {
            throw new HttpException(401, 'INVALID_GOOGLE_TOKEN', 'Token Google kedaluwarsa');
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $verifiedEmail = $payload['email_verified'] ?? false;
        $emailOk = $verifiedEmail === true || $verifiedEmail === 'true';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$emailOk) {
            throw new HttpException(401, 'INVALID_GOOGLE_TOKEN', 'Email Google tidak terverifikasi');
        }

        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            $name = $email;
        }

        if (mb_strlen($name) > 120) {
            $name = mb_substr($name, 0, 120);
        }

        return ['email' => $email, 'name' => $name];
    }

    /**
     * Tukar kode otorisasi (alur popup Google) menjadi profil.
     *
     * @return array{email: string, name: string}
     */
    public static function profileFromCode(string $code, string $clientId, string $clientSecret, string $redirectUri): array
    {
        if ($clientSecret === '') {
            throw new HttpException(503, 'GOOGLE_NOT_CONFIGURED', 'Google OAuth belum diatur. Isi client secret di vendor/config');
        }

        $token = self::httpForm('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        $idToken = (string) ($token['id_token'] ?? '');

        if ($idToken === '') {
            throw new HttpException(401, 'INVALID_GOOGLE_TOKEN', 'Token Google tidak valid');
        }

        return self::verify($idToken, $clientId);
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private static function httpForm(string $url, array $fields): array
    {
        $body = self::httpRequest('POST', $url, http_build_query($fields), 'Masuk dengan Google gagal', 401);
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new HttpException(401, 'INVALID_GOOGLE_TOKEN', 'Masuk dengan Google gagal');
        }

        return $decoded;
    }

    /**
     * @return array<string, string>
     */
    private static function certificates(): array
    {
        $cached = Cache::remember('google', 'certs', 3600, static function (): array {
            $body = self::httpGet(self::CERTS_URL);
            $decoded = json_decode($body, true);

            if (!is_array($decoded) || !isset($decoded['keys']) || !is_array($decoded['keys'])) {
                throw new HttpException(503, 'GOOGLE_UNAVAILABLE', 'Kunci Google tidak dapat diambil');
            }

            $keys = [];

            foreach ($decoded['keys'] as $jwk) {
                if (!is_array($jwk) || ($jwk['kty'] ?? '') !== 'RSA' || !isset($jwk['kid'], $jwk['n'], $jwk['e'])) {
                    continue;
                }

                $keys[(string) $jwk['kid']] = self::jwkToPem((string) $jwk['n'], (string) $jwk['e']);
            }

            if ($keys === []) {
                throw new HttpException(503, 'GOOGLE_UNAVAILABLE', 'Kunci Google tidak dapat diambil');
            }

            return $keys;
        });

        return is_array($cached) ? $cached : [];
    }

    private static function httpGet(string $url): string
    {
        return self::httpRequest('GET', $url, null, 'Kunci Google tidak dapat diambil', 503);
    }

    private static function httpRequest(string $method, string $url, ?string $body, string $failure, int $statusCode): string
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        $scheme = is_array($parts) ? (string) ($parts['scheme'] ?? 'https') : 'https';

        if ($host === '') {
            throw new HttpException($statusCode, 'GOOGLE_UNAVAILABLE', $failure);
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $path = (string) ($parts['path'] ?? '/');

        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }

        $context = stream_context_create([
            'ssl' => [
                'peer_name' => $host,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
            ],
        ]);
        $socket = @stream_socket_client(
            ($scheme === 'https' ? 'ssl' : 'tcp') . '://' . $host . ':' . $port,
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            throw new HttpException($statusCode, 'GOOGLE_UNAVAILABLE', $failure);
        }

        stream_set_timeout($socket, 10);
        $request = $method . ' ' . $path . " HTTP/1.1\r\n"
            . 'Host: ' . $host . "\r\n"
            . "Accept: application/json\r\n"
            . "Accept-Encoding: identity\r\n"
            . "Connection: close\r\n";

        if ($body !== null) {
            $request .= "Content-Type: application/x-www-form-urlencoded\r\n"
                . 'Content-Length: ' . strlen($body) . "\r\n";
        }

        fwrite($socket, $request . "\r\n" . ($body ?? ''));
        $raw = stream_get_contents($socket);
        fclose($socket);

        if (!is_string($raw) || !str_contains($raw, "\r\n\r\n")) {
            throw new HttpException($statusCode, 'GOOGLE_UNAVAILABLE', $failure);
        }

        [$head, $payload] = explode("\r\n\r\n", $raw, 2);

        if (!preg_match('/^HTTP\/\d+(?:\.\d+)? (\d+)/', $head, $match) || $match[1] !== '200') {
            throw new HttpException($statusCode, 'GOOGLE_UNAVAILABLE', $failure);
        }

        if (stripos($head, 'Transfer-Encoding: chunked') !== false) {
            $payload = self::decodeChunked($payload);
        }

        return $payload;
    }

    private static function decodeChunked(string $body): string
    {
        $decoded = '';
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            $lineEnd = strpos($body, "\r\n", $offset);

            if ($lineEnd === false) {
                break;
            }

            $size = hexdec(trim(substr($body, $offset, $lineEnd - $offset)));
            $offset = $lineEnd + 2;

            if ($size === 0) {
                break;
            }

            $decoded .= substr($body, $offset, $size);
            $offset += $size + 2;
        }

        return $decoded;
    }

    private static function jwkToPem(string $modulus, string $exponent): string
    {
        $modulus = self::base64UrlDecode($modulus);
        $exponent = self::base64UrlDecode($exponent);

        if ($modulus === '' || $exponent === '') {
            throw new HttpException(503, 'GOOGLE_UNAVAILABLE', 'Kunci Google tidak dapat diambil');
        }

        if ((ord($modulus[0]) & 0x80) !== 0) {
            $modulus = "\x00" . $modulus;
        }

        if ((ord($exponent[0]) & 0x80) !== 0) {
            $exponent = "\x00" . $exponent;
        }

        $rsa = self::der(0x30, self::der(0x02, $modulus) . self::der(0x02, $exponent));
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        $spki = self::der(0x30, $algorithm . self::der(0x03, "\x00" . $rsa));

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function der(int $tag, string $value): string
    {
        $length = strlen($value);

        if ($length < 128) {
            return chr($tag) . chr($length) . $value;
        }

        $bytes = '';

        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }

        return chr($tag) . chr(0x80 | strlen($bytes)) . $bytes . $value;
    }

    private static function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
