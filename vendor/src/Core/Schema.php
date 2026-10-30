<?php

declare(strict_types=1);

namespace App\Core;

final class Schema
{
    private const TYPES = [
        'string' => 'VARCHAR(%d)',
        'text' => 'TEXT',
        'int' => 'INT',
        'bigint' => 'BIGINT',
        'bool' => 'TINYINT(1)',
        'datetime' => 'DATETIME',
        'decimal' => 'DECIMAL(12,2)',
    ];

    /**
     * Membuat tabel dari metadata Model dan menambah kolom/index yang belum ada.
     * Tidak mengubah tipe atau menghapus kolom lama.
     *
     * @return array{created: bool, columns: array<int, string>, indexes: array<int, string>}
     */
    public static function sync(Database $database, string $model): array
    {
        $table = $model::table();
        $existed = self::tableExists($database, $table);

        $columns = ['`id` INT UNSIGNED NOT NULL AUTO_INCREMENT'];
        $keys = ['PRIMARY KEY (`id`)'];

        foreach ($model::fields() as $name => $field) {
            $columns[] = self::column($name, $field);

            if (($field['unique'] ?? false) === true) {
                $keys[] = 'UNIQUE KEY `uniq_' . $name . '` (`' . $name . '`)';
            }
        }

        $columns[] = '`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $columns[] = '`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';

        $database->pdo()->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (%s) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci',
            $table,
            implode(', ', [...$columns, ...$keys]),
        ));

        $addedColumns = [];
        $addedIndexes = [];

        if ($existed) {
            $existing = self::existingColumns($database, $table);

            if ($model::softDeletes() && !in_array('deleted_at', $existing, true)) {
                $database->pdo()->exec(sprintf(
                    'ALTER TABLE `%s` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL',
                    $table,
                ));
                $addedColumns[] = 'deleted_at';
            }

            foreach ($model::fields() as $name => $field) {
                if (in_array($name, $existing, true)) {
                    continue;
                }

                $database->pdo()->exec(sprintf(
                    'ALTER TABLE `%s` ADD COLUMN %s',
                    $table,
                    self::column($name, $field),
                ));

                $addedColumns[] = $name;
            }

            $uniqueIndexes = self::existingUniqueIndexes($database, $table);

            foreach ($model::fields() as $name => $field) {
                if (($field['unique'] ?? false) !== true || in_array($name, $uniqueIndexes, true)) {
                    continue;
                }

                $database->pdo()->exec(sprintf(
                    'ALTER TABLE `%s` ADD UNIQUE KEY `uniq_%s` (`%s`)',
                    $table,
                    $name,
                    $name,
                ));

                $addedIndexes[] = $name;
            }
        }

        return ['created' => !$existed, 'columns' => $addedColumns, 'indexes' => $addedIndexes];
    }

    /**
     * @param array<string, mixed> $field
     */
    private static function column(string $name, array $field): string
    {
        $type = (string) ($field['type'] ?? 'string');
        $template = self::TYPES[$type] ?? self::TYPES['string'];

        $sqlType = $type === 'string'
            ? sprintf($template, (int) ($field['max'] ?? 255))
            : $template;

        if (($field['unsigned'] ?? false) === true && ($type === 'int' || $type === 'bigint')) {
            $sqlType .= ' UNSIGNED';
        }

        $nullable = ($field['required'] ?? false) === true ? 'NOT NULL' : 'NULL';

        $default = '';

        if (array_key_exists('default', $field)) {
            $value = $field['default'];
            $default = ' DEFAULT ' . ($value === null ? 'NULL' : "'" . addslashes((string) $value) . "'");
        }

        return sprintf('`%s` %s %s%s', $name, $sqlType, $nullable, $default);
    }

    /**
     * Menambahkan foreign key untuk relasi belongsTo yang kolomnya sudah ada.
     *
     * @return array<int, string>
     */
    public static function syncForeignKeys(Database $database, string $model): array
    {
        $table = $model::table();
        $added = [];

        foreach ($model::relations() as $relation) {
            if (($relation['type'] ?? 'belongsTo') !== 'belongsTo') {
                continue;
            }

            /** @var class-string<Model> $related */
            $related = (string) $relation['model'];
            $column = (string) $relation['foreignKey'];
            $constraint = substr('fk_' . $table . '_' . $column, 0, 64);

            $exists = $database->first(
                'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = :name',
                ['name' => $constraint],
            );

            if ((int) ($exists['total'] ?? 0) > 0) {
                continue;
            }

            $field = $model::fields()[$column] ?? [];
            $onDelete = ($field['required'] ?? false) === true ? 'RESTRICT' : 'SET NULL';

            $database->pdo()->exec(sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`id`) ON DELETE %s ON UPDATE CASCADE',
                $table,
                $constraint,
                $column,
                $related::table(),
                $onDelete,
            ));

            $added[] = $constraint;
        }

        return $added;
    }

    private static function tableExists(Database $database, string $table): bool
    {
        $row = $database->first(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
            ['table' => $table],
        );

        return (int) ($row['total'] ?? 0) > 0;
    }

    /**
     * @return array<int, string>
     */
    private static function existingColumns(Database $database, string $table): array
    {
        $rows = $database->select(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
            ['table' => $table],
        );

        return array_map(static fn (array $row): string => (string) $row['COLUMN_NAME'], $rows);
    }

    /**
     * @return array<int, string>
     */
    private static function existingUniqueIndexes(Database $database, string $table): array
    {
        $rows = $database->select(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND NON_UNIQUE = 0',
            ['table' => $table],
        );

        return array_map(static fn (array $row): string => (string) $row['COLUMN_NAME'], $rows);
    }
}