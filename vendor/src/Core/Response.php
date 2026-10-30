<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    private function __construct(
        private readonly string $body,
        private readonly int $statusCode,
        private readonly array $headers,
    ) {
    }

    public static function json(mixed $data, int $statusCode = 200): self
    {
        return new self(
            (string) json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $statusCode,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public static function error(string $code, string $message, int $statusCode): self
    {
        return new self(
            (string) json_encode(
                ['ok' => false, 'error' => ['code' => $code, 'message' => $message]],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            $statusCode,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public static function html(string $body, int $statusCode = 200): self
    {
        return new self(
            $body,
            $statusCode,
            ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    public static function raw(string $body, int $statusCode, array $headers = []): self
    {
        return new self($body, $statusCode, $headers);
    }

    public static function file(string $path, string $mime): self
    {
        return new self(
            (string) file_get_contents($path),
            200,
            ['Content-Type' => $mime, 'Cache-Control' => 'public, max-age=86400'],
        );
    }

    public static function noContent(): self
    {
        return new self('', 204, []);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true, $this->statusCode);
        }

        echo $this->body;
    }
}