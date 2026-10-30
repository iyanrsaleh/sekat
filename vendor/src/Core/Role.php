<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Satu akun satu role. Nama yang sah ada di config/roles.php.
 * Halaman daftar hanya membuat "user". "admin" dibuat lewat seed.
 */
final class Role
{
    public const USER = 'user';

    public const ADMIN = 'admin';

    /** @var list<string>|null */
    private static ?array $cached = null;

    /**
     * @return list<string>
     */
    public static function catalog(): array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $path = dirname(__DIR__, 2) . '/config/roles.php';
        $loaded = is_file($path) ? require $path : [];
        $names = [];

        foreach (is_array($loaded) ? $loaded : [] as $name) {
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        if (!in_array(self::USER, $names, true)) {
            $names[] = self::USER;
        }

        if (!in_array(self::ADMIN, $names, true)) {
            $names[] = self::ADMIN;
        }

        self::$cached = array_values(array_unique($names));

        return self::$cached;
    }

    public static function known(string $role): bool
    {
        return in_array($role, self::catalog(), true);
    }
}
