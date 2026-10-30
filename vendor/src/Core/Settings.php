<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Akses pengaturan aplikasi (tabel settings) untuk lapisan web.
 * Dipakai PageController/DashboardController untuk judul halaman.
 */
final class Settings
{
    /** @var array<string, string>|null */
    private static ?array $values = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default;
    }

    public static function siteTitle(): string
    {
        $title = self::all()['site_title'] ?? '';

        if ($title !== '') {
            return $title;
        }

        return self::all()['app_name'] ?? 'Sekat';
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$values !== null) {
            return self::$values;
        }

        try {
            $database = Database::fromConfigFile(self::configPath());
            self::$values = (new \App\Repository\SettingRepository($database))->all();
        } catch (\Throwable) {
            self::$values = \App\Repository\SettingRepository::DEFAULTS;
        }

        return self::$values;
    }

    private static function configPath(): string
    {
        $root = defined('FRAMEWORK_ROOT') ? (string) FRAMEWORK_ROOT : dirname(__DIR__, 2);

        return $root . '/vendor/config/database.php';
    }
}