<?php

declare(strict_types=1);

namespace App\Core;

final class ErrorHandler
{
    public static function register(bool $debug, string $logDirectory): void
    {
        ini_set('display_errors', '0');
        error_reporting(E_ALL);

        set_exception_handler(static function (\Throwable $e) use ($debug, $logDirectory): void {
            self::log($e, $logDirectory);

            if ($e instanceof HttpException) {
                $e->toResponse()->send();
                return;
            }

            $message = $debug ? $e->getMessage() : 'Terjadi kesalahan pada server';

            Response::error('INTERNAL_ERROR', $message, 500)->send();
        });
    }

    private static function log(\Throwable $e, string $logDirectory): void
    {
        if (!is_dir($logDirectory) && !mkdir($logDirectory, 0775, true) && !is_dir($logDirectory)) {
            return;
        }

        $line = sprintf(
            "[%s] %s: %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        @file_put_contents($logDirectory . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}