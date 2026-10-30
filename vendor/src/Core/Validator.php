<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validator terpusat. Pakai di Model (lewat field 'rules') atau mandiri.
 *
 * Aturan didukung (pipe `|` atau koma):
 *   required, nullable, min:N, max:N, email, numeric, integer,
 *   between:min,max, regex:/pola/, enum:a,b,c, date, confirmed
 *
 * Contoh di Model:
 *   'email' => ['type'=>'string','max'=>190,'required'=>true,'rules'=>['email','max:190']]
 *   'umur'  => ['type'=>'int','rules'=>['integer','between:17,60']]
 *   'status'=> ['type'=>'string','rules'=>['enum:draft,published,archived']]
 */
final class Validator
{
    /**
     * Memvalidasi satu nilai.
     *
     * @param mixed $value
     * @param array<int, string>|string $rules
     * @param array<string, mixed> $context data lain (untuk confirmed)
     * @return string|null pesan error, null bila lolos
     */
    public static function check(mixed $value, array|string $rules, array $context = []): ?string
    {
        $rules = self::normalize($rules);

        foreach ($rules as $rule) {
            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

            $message = self::run($name, $param, $value, $context);

            if ($message !== null) {
                return $message;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function normalize(array|string $rules): array
    {
        if (is_array($rules)) {
            return $rules;
        }

        $rules = str_replace(',', '|', $rules);

        return array_values(array_filter(array_map('trim', explode('|', $rules))));
    }

    private static function run(string $name, ?string $param, mixed $value, array $context): ?string
    {
        if ($value === null || $value === '') {
            return ($name === 'required') ? 'wajib diisi' : null;
        }

        $length = is_string($value) ? mb_strlen($value) : 0;

        return match ($name) {
            'nullable' => null,
            'min' => $length < (int) $param ? 'minimal ' . $param . ' karakter' : null,
            'max' => $length > (int) $param ? 'maksimal ' . $param . ' karakter' : null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? 'format email tidak valid' : null,
            'numeric' => !is_numeric($value) ? 'harus berupa angka' : null,
            'integer' => filter_var($value, FILTER_VALIDATE_INT) === false ? 'harus berupa bilangan bulat' : null,
            'between' => self::between($value, $param),
            'regex' => $param !== null && @preg_match($param, (string) $value) !== 1 ? 'tidak sesuai pola' : null,
            'enum' => self::enum($value, $param),
            'date' => self::isDate($value) ? null : 'format tanggal tidak valid',
            'confirmed' => self::confirmed($value, $context),
            'required' => null,
            default => null,
        };
    }

    private static function between(mixed $value, ?string $param): ?string
    {
        if ($param === null || !is_numeric($value)) {
            return null;
        }

        [$min, $max] = array_pad(explode(',', $param), 2, null);

        if ($min !== null && $max !== null && ((float) $value < (float) $min || (float) $value > (float) $max)) {
            return 'antara ' . $min . ' dan ' . $max;
        }

        return null;
    }

    private static function enum(mixed $value, ?string $param): ?string
    {
        if ($param === null) {
            return null;
        }

        $allowed = array_map('trim', explode(',', $param));

        return in_array((string) $value, $allowed, true) ? null : 'nilai tidak diizinkan';
    }

    private static function confirmed(mixed $value, array $context): ?string
    {
        $field = array_search($value, $context, true);

        return $field === false ? 'konfirmasi tidak cocok' : null;
    }

    private static function isDate(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        try {
            new \DateTimeImmutable($value);
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}