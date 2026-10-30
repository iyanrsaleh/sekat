<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $attributes = [];

    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $server,
        private readonly string $rawBody,
    ) {
    }

    public static function capture(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

        if ($script !== '' && str_starts_with($path, $script)) {
            $path = substr($path, strlen($script));
        } else {
            $base = rtrim(str_replace('\\', '/', dirname($script)), '/');

            if ($base !== '' && $base !== '.' && str_starts_with($path, $base)) {
                $path = substr($path, strlen($base));
            }
        }

        $path = '/' . trim($path, '/');

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path,
            $_GET,
            $_SERVER,
            (string) file_get_contents('php://input'),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $_COOKIE[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function queryAll(): array
    {
        return $this->query;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (isset($this->server[$key]) && $this->server[$key] !== '') {
            return (string) $this->server[$key];
        }

        if (strcasecmp($name, 'Authorization') === 0 && !empty($this->server['REDIRECT_HTTP_AUTHORIZATION'])) {
            return (string) $this->server['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (strtoupper($name) === 'CONTENT_TYPE' && isset($this->server['CONTENT_TYPE'])) {
            return (string) $this->server['CONTENT_TYPE'];
        }

        foreach ($this->allHeaders() as $header => $value) {
            if (strcasecmp($header, $name) === 0) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Sebagian setup Apache tidak mengisi HTTP_AUTHORIZATION di $_SERVER,
     * tetapi header tetap terlihat lewat getallheaders().
     *
     * @return array<string, string>
     */
    private function allHeaders(): array
    {
        if (!function_exists('getallheaders')) {
            return [];
        }

        $headers = getallheaders();

        return is_array($headers) ? $headers : [];
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');

        if ($header === null) {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if (trim($this->rawBody) === '') {
            return [];
        }

        try {
            $data = json_decode($this->rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new HttpException(400, 'INVALID_JSON', 'Body request bukan JSON yang valid');
        }

        if (!is_array($data)) {
            throw new HttpException(400, 'INVALID_JSON', 'Body request harus berupa objek JSON');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * Akar situs sesuai alamat yang sedang dibuka.
     * http://localhost/framework menjadi http://localhost/framework
     * http://framework.local menjadi http://framework.local
     */
    public static function appOrigin(): string
    {
        $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwarded === 'https';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
        if ($base === '/' || $base === '.') {
            $base = '';
        }

        return ($https ? 'https' : 'http') . '://' . $host . $base;
    }
}