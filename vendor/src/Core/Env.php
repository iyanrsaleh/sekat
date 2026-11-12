<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Nilai .env untuk request ini.
 *
 * Tidak memakai putenv(): di Apache mod_php thread-safe (Nxserver) putenv berlaku untuk seluruh
 * proses, sehingga .env aplikasi lain yang sedang melayani request di Apache yang sama ikut
 * terbaca (DB_NAME, DB_PASS, APP_KEY, ...). Properti static PHP bersifat per request.
 *
 * Urutan baca: key yang ada di .env (termasuk yang sengaja dikosongkan) > environment server
 * (getenv, mis. env panel hosting / SetEnv) > default. Daftarkan semua key yang dipakai di .env
 * (boleh kosong) agar nilai dari luar tidak pernah terbaca.
 */
final class Env
{
    /** @var array<string, string>|null */
    private static ?array $values = null;

    public static function load(string $file): void
    {
        self::$values = is_file($file) ? self::parse((string) file_get_contents($file)) : [];

        foreach (self::$values as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$values !== null && array_key_exists($key, self::$values)) {
            $value = self::$values[$key];

            return $value === '' ? $default : $value;
        }

        // Sebagian hosting (mis. aaPanel) menonaktifkan getenv/putenv lewat disable_functions.
        $value = function_exists('getenv') ? getenv($key) : false;

        return $value === false || $value === '' ? $default : $value;
    }

    /**
     * @return array<string, string>
     */
    public static function parse(string $content): array
    {
        $values = [];

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $split = strpos($line, '=');

            if ($split === false) {
                continue;
            }

            $key = trim(substr($line, 0, $split));
            $value = trim(substr($line, $split + 1));

            if ($key === '') {
                continue;
            }

            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && str_ends_with($value, $value[0])) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
        }

        return $values;
    }
}
