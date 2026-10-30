<?php

declare(strict_types=1);

namespace App\Proxy;

/**
 * Meneruskan permintaan HTTP ke mesin lain.
 * Header yang diberikan pemanggil dikirim apa adanya, termasuk Authorization.
 * Redirect tidak diikuti. Rute proxy publik membuang Authorization sebelum memanggil kelas ini.
 */
final class ProxyClient
{
    /**
     * POST JSON ke host lain. Header yang dikirim ikut utuh, termasuk Authorization.
     *
     * @param array<string, string> $headers
     * @return array{status: int, body: string, contentType: string|null}
     */
    public function postJson(string $url, array $headers, string $body, int $timeout = 20): array
    {
        return $this->forward('POST', $url, $headers, $body, $timeout);
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string, contentType: string|null}
     */
    public function forward(
        string $method,
        string $url,
        array $headers,
        string $body,
        int $timeout = 15,
    ): array {
        if (!function_exists('curl_init')) {
            return $this->forwardSocket($method, $url, $headers, $body, $timeout);
        }

        $handle = curl_init($url);

        $curlHeaders = [];

        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_TIMEOUT => max(1, $timeout),
            CURLOPT_CONNECTTIMEOUT => min(5, max(1, $timeout)),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$contentType): int {
                $trimmed = trim($line);
                if (stripos($trimmed, 'Content-Type:') === 0) {
                    $contentType = trim(substr($trimmed, strlen('Content-Type:')));
                }
                return strlen($line);
            },
        ];

        $caBundle = $this->caBundle();

        if ($caBundle !== null) {
            $options[CURLOPT_CAINFO] = $caBundle;
        } else {
            // Tanpa CA bundle: verifikasi dimatikan (hanya untuk mesin lokal).
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        curl_setopt_array($handle, $options);

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = (string) curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($status === 0 || $error !== '') {
            throw new \RuntimeException($error === '' ? 'Tujuan tidak menjawab' : $error);
        }

        return [
            'status' => $status,
            'body' => $responseBody,
            'contentType' => $contentType ?? null,
        ];
    }

    /**
     * Dipakai bila ekstensi curl tidak termuat. Tidak mengikuti redirect.
     *
     * @param array<string, string> $headers
     * @return array{status: int, body: string, contentType: string|null}
     */
    private function forwardSocket(string $method, string $url, array $headers, string $body, int $timeout = 15): array
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';

        if (!is_string($host) || $host === '') {
            throw new \RuntimeException('Tujuan tidak menjawab');
        }

        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] : 'http';
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        if (isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }

        $ssl = ['SNI_enabled' => true, 'peer_name' => $host];
        $caBundle = $this->caBundle();

        if ($caBundle !== null) {
            $ssl['verify_peer'] = true;
            $ssl['verify_peer_name'] = true;
            $ssl['cafile'] = $caBundle;
        } else {
            $ssl['verify_peer'] = false;
            $ssl['verify_peer_name'] = false;
        }

        // stream_socket_client(nama host) memakai getaddrinfo Windows, yang
        // bisa mengembalikan alamat lain dari gethostbyname dan tidak menjawab.
        // Sambungkan ke IPv4 hasil resolver PHP; SNI dan Host tetap nama asli.
        $targets = [];
        $resolved = gethostbyname($host);
        if (filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $targets[] = $resolved;
        }
        if (!in_array($host, $targets, true)) {
            $targets[] = $host;
        }

        $errno = 0;
        $error = '';
        $socket = false;
        foreach ($targets as $target) {
            $remote = ($scheme === 'https' ? 'ssl' : 'tcp') . '://' . $target . ':' . $port;
            $socket = stream_socket_client(
                $remote,
                $errno,
                $error,
                5,
                STREAM_CLIENT_CONNECT,
                stream_context_create(['ssl' => $ssl])
            );
            if ($socket !== false) {
                break;
            }
        }

        if ($socket === false) {
            throw new \RuntimeException($error === '' ? 'Tujuan tidak menjawab' : $error);
        }

        stream_set_timeout($socket, max(1, $timeout));

        $lines = ['Host: ' . $host . ($this->defaultPort($scheme, $port) ? '' : ':' . $port), 'Connection: close'];

        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Host') === 0 || strcasecmp($name, 'Connection') === 0) {
                continue;
            }
            $lines[] = $name . ': ' . $value;
        }

        $payload = '';
        if ($body !== '' && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $lines[] = 'Content-Length: ' . strlen($body);
            $payload = $body;
        }

        $written = fwrite($socket, $method . ' ' . $path . " HTTP/1.1\r\n" . implode("\r\n", $lines) . "\r\n\r\n" . $payload);
        if ($written === false) {
            fclose($socket);
            throw new \RuntimeException('Tujuan tidak menjawab');
        }

        $raw = stream_get_contents($socket);
        fclose($socket);

        if (!is_string($raw) || $raw === '' || !preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d+)/', $raw, $statusMatch)) {
            throw new \RuntimeException('Tujuan tidak menjawab');
        }

        $splitAt = strpos($raw, "\r\n\r\n");
        $headerText = $splitAt === false ? $raw : substr($raw, 0, $splitAt);
        $responseBody = $splitAt === false ? '' : substr($raw, $splitAt + 4);

        if (preg_match('/^Transfer-Encoding:\s*chunked/mi', $headerText) === 1) {
            $responseBody = $this->decodeChunked($responseBody);
        }

        if (preg_match('/^Content-Encoding:\s*gzip/mi', $headerText) === 1) {
            $decoded = gzdecode($responseBody);
            if (is_string($decoded)) {
                $responseBody = $decoded;
            }
        }

        $contentType = null;
        if (preg_match('/^Content-Type:\s*(.+)$/mi', $headerText, $typeMatch) === 1) {
            $contentType = trim($typeMatch[1]);
        }

        return [
            'status' => (int) $statusMatch[1],
            'body' => $responseBody,
            'contentType' => $contentType,
        ];
    }

    private function defaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
    }

    private function decodeChunked(string $body): string
    {
        $decoded = '';
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            $lineEnd = strpos($body, "\r\n", $offset);
            if ($lineEnd === false) {
                break;
            }

            $sizeToken = trim(substr($body, $offset, $lineEnd - $offset));
            $sizeToken = explode(';', $sizeToken, 2)[0];
            $size = hexdec(trim($sizeToken));
            if ($size < 1) {
                break;
            }

            $offset = $lineEnd + 2;
            $decoded .= substr($body, $offset, $size);
            $offset += $size + 2;
        }

        return $decoded;
    }

    /**
     * Mencari CA bundle agar HTTPS ke tujuan bisa diverifikasi.
     */
    private function caBundle(): ?string
    {
        $candidates = [
            getenv('CURL_CA_BUNDLE') ?: null,
            ini_get('curl.cainfo') ?: null,
            ini_get('openssl.cafile') ?: null,
            dirname(PHP_BINARY) . '/cacert.pem',
            dirname(PHP_BINARY) . '/extras/ssl/cacert.pem',
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}