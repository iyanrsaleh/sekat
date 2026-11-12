<?php

declare(strict_types=1);

namespace App\Install;

final class EnvironmentWriter
{
    public function __construct(
        private readonly string $root,
    ) {
    }

    public function write(array $db): void
    {
        $example = $this->root . '/.env.example';
        $target = $this->root . '/.env';

        if (!is_file($example)) {
            throw new \RuntimeException('.env.example tidak ditemukan');
        }

        $content = (string) file_get_contents($example);

        $replacements = [
            'DB_HOST=' => 'DB_HOST=' . $db['host'],
            'DB_PORT=' => 'DB_PORT=' . $db['port'],
            'DB_NAME=' => 'DB_NAME=' . $db['database'],
            'DB_USER=' => 'DB_USER=' . $db['username'],
            'DB_PASS=' => 'DB_PASS=' . $db['password'],
        ];

        foreach ($replacements as $search => $replace) {
            $content = preg_replace(
                '/^' . preg_quote($search, '/') . '.*$/m',
                $replace,
                $content,
            );
        }

        $written = @file_put_contents($target, $content, LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException('Gagal menulis .env');
        }

        @chmod($target, 0640);
    }

    public function exists(): bool
    {
        return is_file($this->root . '/.env');
    }

    public function hasDatabaseCredentials(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        $content = (string) file_get_contents($this->root . '/.env');
        $pass = (string) (preg_match('/^DB_PASS=(.*)$/m', $content, $m) === 1 ? trim($m[1]) : '');

        return $pass !== '';
    }
}
