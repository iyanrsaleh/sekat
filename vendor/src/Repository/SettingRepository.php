<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Cache;
use App\Core\Database;
use App\Core\HttpException;

final class SettingRepository
{
    /** Kunci yang boleh diubah + default. */
    public const DEFAULTS = [
        'app_name' => 'Sekat',
        'site_title' => 'Sekat',
        'contact_email' => '',
        'timezone' => 'Asia/Jakarta',
    ];

    /** Kunci yang boleh dibaca publik. */
    public const PUBLIC_KEYS = ['app_name', 'site_title'];

    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return Cache::remember('settings', 'all', 60, function (): array {
            $values = self::DEFAULTS;

            foreach ($this->database->select('SELECT `key`, `value` FROM settings') as $row) {
                $key = (string) $row['key'];

                if (array_key_exists($key, self::DEFAULTS)) {
                    $values[$key] = (string) ($row['value'] ?? '');
                }
            }

            return $values;
        });
    }

    /**
     * @return array<string, string>
     */
    public function publicValues(): array
    {
        $all = $this->all();
        $output = [];

        foreach (self::PUBLIC_KEYS as $key) {
            $output[$key] = $all[$key] ?? '';
        }

        return $output;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function update(array $input): array
    {
        if ($input === []) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Tidak ada pengaturan yang dikirim');
        }

        foreach ($input as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, self::DEFAULTS)) {
                throw new HttpException(422, 'UNKNOWN_SETTING', 'Kunci pengaturan tidak dikenal: ' . (string) $key);
            }

            if (!is_scalar($value) && $value !== null) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Nilai ' . $key . ' tidak valid');
            }

            $value = trim((string) $value);

            if (mb_strlen($value) > 2000) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Nilai ' . $key . ' terlalu panjang');
            }

            if ($key === 'contact_email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Email kontak tidak valid');
            }

            $this->database->execute(
                'INSERT INTO settings (`key`, `value`) VALUES (:key, :value)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
                ['key' => $key, 'value' => $value],
            );
        }

        Cache::flush('settings');

        return $this->all();
    }
}