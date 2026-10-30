<?php

declare(strict_types=1);

namespace App\Core;

final class RateLimiter
{
    public function __construct(private readonly string $directory)
    {
    }

    /**
     * true = boleh lanjut, false = sudah melewati batas.
     */
    public function allow(string $key, int $max, int $windowSeconds): bool
    {
        $file = $this->path($key);
        $now = time();
        $state = ['start' => $now, 'count' => 0];

        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);

            if (is_array($decoded) && isset($decoded['start'], $decoded['count'])) {
                $elapsed = $now - (int) $decoded['start'];

                if ($elapsed >= 0 && $elapsed < $windowSeconds) {
                    $state = ['start' => (int) $decoded['start'], 'count' => (int) $decoded['count']];
                }
            }
        }

        $state['count']++;

        $this->write($file, $state);

        return $state['count'] <= $max;
    }

    private function path(string $key): string
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }

        return $this->directory . '/' . hash('sha256', $key) . '.json';
    }

    /**
     * @param array{start: int, count: int} $state
     */
    private function write(string $file, array $state): void
    {
        file_put_contents($file, (string) json_encode($state), LOCK_EX);
    }
}