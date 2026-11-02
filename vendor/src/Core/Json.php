<?php

declare(strict_types=1);

namespace App\Core;

/**
 * JSON yang mempertahankan objek kosong.
 * json_decode(..., true) mengubah {} menjadi [] dan merusak klien yang mengharuskan objek.
 */
final class Json
{
    public static function encode(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function decode(string $json): mixed
    {
        $value = json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return self::keepEmptyObjects($value);
    }

    private static function keepEmptyObjects(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $vars = get_object_vars($value);
            if ($vars === []) {
                return $value;
            }

            $out = [];
            foreach ($vars as $key => $item) {
                $out[$key] = self::keepEmptyObjects($item);
            }

            return $out;
        }

        if (is_array($value)) {
            foreach ($value as $index => $item) {
                $value[$index] = self::keepEmptyObjects($item);
            }
        }

        return $value;
    }
}
