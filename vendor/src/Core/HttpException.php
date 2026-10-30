<?php

declare(strict_types=1);

namespace App\Core;

class HttpException extends \RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function toResponse(): Response
    {
        return Response::error($this->errorCode, $this->getMessage(), $this->statusCode);
    }
}