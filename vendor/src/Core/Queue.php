<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Antrian job berbasis file.
 * pending → working (rename atomik) → selesai (hapus) atau failed/.
 */
final class Queue
{
    private static ?string $directory = null;

    public static function setDirectory(string $directory): void
    {
        self::$directory = rtrim($directory, '/\\');
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function push(string $type, array $payload = []): string
    {
        $dir = self::dir('pending');
        $id = bin2hex(random_bytes(16));
        $path = $dir . '/' . $id . '.json';
        $body = [
            'id' => $id,
            'type' => $type,
            'payload' => $payload,
            'attempts' => 0,
            'created_at' => date(DATE_ATOM),
        ];

        $written = file_put_contents(
            $path,
            (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        );

        if ($written === false) {
            throw new \RuntimeException('Gagal menulis job antrian');
        }

        return $id;
    }

    /**
     * Ambil satu job pending (rename ke working). Null bila kosong.
     *
     * @return array{id: string, type: string, payload: array<string, mixed>, attempts: int, created_at: string, path: string}|null
     */
    public static function claim(): ?array
    {
        $pending = self::dir('pending');
        $working = self::dir('working');
        $files = glob($pending . '/*.json') ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            $target = $working . '/' . $name;

            if (!@rename($file, $target)) {
                continue;
            }

            $raw = file_get_contents($target);
            $data = is_string($raw) ? json_decode($raw, true) : null;

            if (!is_array($data) || !isset($data['id'], $data['type'])) {
                @unlink($target);

                continue;
            }

            $data['attempts'] = (int) ($data['attempts'] ?? 0) + 1;
            $data['path'] = $target;
            $data['payload'] = is_array($data['payload'] ?? null) ? $data['payload'] : [];
            file_put_contents(
                $target,
                (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX,
            );

            /** @var array{id: string, type: string, payload: array<string, mixed>, attempts: int, created_at: string, path: string} $data */
            return $data;
        }

        return null;
    }

    /**
     * @param array{path?: string, id?: string} $job
     */
    public static function done(array $job): void
    {
        $path = self::jobPath($job);

        if ($path !== null && is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @param array{path?: string, id?: string} $job
     */
    public static function fail(array $job, string $error = ''): void
    {
        $path = self::jobPath($job);

        if ($path === null || !is_file($path)) {
            return;
        }

        $raw = file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : [];
        if (!is_array($data)) {
            $data = [];
        }
        $data['failed_at'] = date(DATE_ATOM);
        $data['error'] = $error;
        $name = basename($path);
        $target = self::dir('failed') . '/' . $name;
        file_put_contents(
            $target,
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        );
        @unlink($path);
    }

    public static function pendingCount(): int
    {
        return count(glob(self::dir('pending') . '/*.json') ?: []);
    }

    private static function root(): string
    {
        if (self::$directory === null) {
            throw new \RuntimeException('Queue directory belum diset.');
        }

        return self::$directory;
    }

    private static function dir(string $name): string
    {
        $dir = self::root() . '/' . $name;

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Gagal membuat folder antrian: ' . $name);
        }

        return $dir;
    }

    /**
     * @param array{path?: string, id?: string} $job
     */
    private static function jobPath(array $job): ?string
    {
        if (isset($job['path']) && is_string($job['path']) && $job['path'] !== '') {
            return $job['path'];
        }

        if (isset($job['id']) && is_string($job['id']) && $job['id'] !== '') {
            $candidate = self::dir('working') . '/' . $job['id'] . '.json';

            return is_file($candidate) ? $candidate : null;
        }

        return null;
    }
}
