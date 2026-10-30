<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cache file sederhana dengan namespace.
 * Data disimpan sebagai JSON di storage/cache/{namespace}/{hash}.json.
 */
final class Cache
{
    private static ?string $directory = null;

    public static function setDirectory(string $directory): void
    {
        self::$directory = rtrim($directory, '/\\');
    }

    /**
     * Mengambil nilai cache, atau mengisi bila belum ada / sudah kedaluwarsa.
     */
    public static function remember(string $namespace, string $key, int $ttlSeconds, callable $callback): mixed
    {
        $path = self::path($namespace, $key);

        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);

            if (is_array($data) && isset($data['expires'], $data['value']) && time() < (int) $data['expires']) {
                return $data['value'];
            }
        }

        $value = $callback();

        self::put($path, $value, $ttlSeconds);

        return $value;
    }

    /**
     * Menghapus seluruh cache dalam namespace tertentu.
     */
    public static function flush(string $namespace): void
    {
        if (self::$directory === null) {
            return;
        }

        $dir = self::$directory . '/' . str_replace(['\\', '/', '..'], '_', $namespace);

        if (is_dir($dir)) {
            foreach (glob($dir . '/*.json') ?: [] as $file) {
                unlink($file);
            }
        }
    }

    private static function path(string $namespace, string $key): string
    {
        if (self::$directory === null) {
            throw new \RuntimeException('Cache directory belum diset.');
        }

        $dir = self::$directory . '/' . str_replace(['\\', '/', '..'], '_', $namespace);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir . '/' . hash('sha256', $key) . '.json';
    }

    private static function put(string $path, mixed $value, int $ttlSeconds): void
    {
        file_put_contents(
            $path,
            (string) json_encode(['expires' => time() + $ttlSeconds, 'value' => $value]),
            LOCK_EX,
        );
    }
}