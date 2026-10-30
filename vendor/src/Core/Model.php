<?php

declare(strict_types=1);

namespace App\Core;

abstract class Model
{
    /** Nama tabel di database. */
    protected static string $table = '';

    /**
     * Definisi field:
     *   'nama' => ['type' => 'string|text|int|bool|datetime|decimal', 'required' => true, 'max' => 200, 'default' => null, 'unique' => false]
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $fields = [];

    /** Field yang tidak dikirim ke API. */
    protected static array $hidden = [];

    /** false = tabel dibuat, tetapi tidak ada route CRUD otomatis. */
    protected static bool $exposed = true;

    /** true = DELETE hanya mengisi deleted_at, data tidak dihapus fisik. */
    protected static bool $softDelete = false;

    /**
     * Relasi opsional:
     *   'user' => ['type' => 'belongsTo', 'model' => User::class, 'foreignKey' => 'user_id']
     *   'comments' => ['type' => 'hasMany', 'model' => Comment::class, 'foreignKey' => 'post_id']
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $relations = [];

    public static function table(): string
    {
        if (static::$table === '') {
            throw new \RuntimeException(static::class . ' belum mendefinisikan $table');
        }

        return static::$table;
    }

    /** Nama resource di URL: /api/{resource}. */
    public static function softDeletes(): bool
    {
        return static::$softDelete;
    }

    public static function exposed(): bool
    {
        return static::$exposed;
    }

    /** Nama resource di URL: /api/{resource}. */
    public static function resource(): string
    {
        return static::table();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fields(): array
    {
        return static::$fields;
    }

    /**
     * @return array<int, string>
     */
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function relations(): array
    {
        return static::$relations;
    }

    /**
     * @return array<int, string>
     */
    public static function fieldNames(): array
    {
        return array_keys(static::$fields);
    }

    /**
     * @return array<int, string>
     */
    public static function hiddenFields(): array
    {
        return static::$hidden;
    }

    public static function castValue(string $name, mixed $value): mixed
    {
        return match (static::$fields[$name]['type'] ?? 'string') {
            'int' => (int) $value,
            'bool' => (bool) $value,
            'decimal' => (float) $value,
            default => (string) $value,
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function serialize(array $row): array
    {
        $output = [];

        foreach ($row as $key => $value) {
            if (in_array($key, static::$hidden, true)) {
                continue;
            }

            $output[$key] = in_array($key, static::fieldNames(), true)
                ? static::castValue($key, $value)
                : $value;
        }

        return $output;
    }
}