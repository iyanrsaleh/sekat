<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Validator;

/**
 * Repository generik untuk semua Model.
 * Tidak perlu menulis repository per fitur.
 */
final class CrudRepository
{
    /**
     * @param class-string<\App\Core\Model> $model
     */
    public function __construct(
        private readonly Database $database,
        private readonly string $model,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(array $with = []): array
    {
        $model = $this->model;
        $rows = $this->database->select(
            sprintf('SELECT * FROM `%s` ORDER BY id DESC', $model::table())
        );

        return array_map(fn (array $row): array => $this->attach($model::serialize($row), $with), $rows);
    }

    /**
     * Daftar dengan pagination, filter, dan pengurutan.
     *
     * @param array<string, string> $filters field => nilai
     * @return array{items: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function paginate(
        int $page = 1,
        int $perPage = 20,
        array $filters = [],
        string $sort = 'id',
        string $order = 'desc',
        array $with = [],
        bool $publishedOnly = false,
    ): array {
        $model = $this->model;
        $table = $model::table();

        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $sort = in_array($sort, [...$model::fieldNames(), 'id', 'created_at', 'updated_at'], true) ? $sort : 'id';
        $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

        [$where, $params] = $this->where($filters, $publishedOnly);

        $totalRow = $this->database->first(
            sprintf('SELECT COUNT(*) AS total FROM `%s`%s', $table, $where),
            $params,
        );

        $total = (int) ($totalRow['total'] ?? 0);

        $rows = $this->database->select(
            sprintf(
                'SELECT * FROM `%s`%s ORDER BY `%s` %s LIMIT %d OFFSET %d',
                $table,
                $where,
                $sort,
                $order,
                $perPage,
                $offset,
            ),
            $params,
        );

        return [
            'items' => array_map(fn (array $row): array => $this->attach($model::serialize($row), $with), $rows),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id, array $with = [], bool $withDeleted = false): ?array
    {
        $model = $this->model;
        $sql = sprintf('SELECT * FROM `%s` WHERE id = :id', $model::table());

        if ($model::softDeletes() && !$withDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $row = $this->database->first($sql, ['id' => $id]);

        return $row === null ? null : $this->attach($model::serialize($row), $with);
    }

    /**
     * Baris yang boleh dibaca orang luar: status published dan sudah mencapai tanggal tayang.
     *
     * @param array<int, string> $with
     * @return array<string, mixed>|null
     */
    public function findPublished(int $id, array $with = []): ?array
    {
        $model = $this->model;

        if (!in_array('status', $model::fieldNames(), true)) {
            return $this->find($id, $with);
        }

        $sql = sprintf(
            "SELECT * FROM `%s` WHERE id = :id AND status = 'published'",
            $model::table(),
        );

        if ($model::softDeletes()) {
            $sql .= ' AND deleted_at IS NULL';
        }

        if (in_array('published_at', $model::fieldNames(), true)) {
            $sql .= ' AND (published_at IS NULL OR published_at <= NOW())';
        }

        $row = $this->database->first($sql, ['id' => $id]);

        return $row === null ? null : $this->attach($model::serialize($row), $with);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $data = $this->withSlug($this->validated($input), $input, null);
        $columns = array_keys($data);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $this->model::table(),
            implode('`, `', $columns),
            implode(', ', array_map(static fn (string $column): string => ':' . $column, $columns)),
        );

        try {
            $this->database->execute($sql, $data);
        } catch (\PDOException $exception) {
            $this->rethrowDuplicate($exception);
        }

        return $this->find($this->database->lastInsertId()) ?? [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $input): ?array
    {
        if ($this->find($id) === null) {
            return null;
        }

        $data = $this->withSlug($this->validated($input, $id), $input, $id);

        if ($data !== []) {
            $assignments = implode(', ', array_map(
                static fn (string $column): string => sprintf('`%s` = :%s', $column, $column),
                array_keys($data),
            ));

            try {
                $this->database->execute(
                    sprintf('UPDATE `%s` SET %s WHERE id = :id', $this->model::table(), $assignments),
                    [...$data, 'id' => $id],
                );
            } catch (\PDOException $exception) {
                $this->rethrowDuplicate($exception);
                throw $exception;
            }
        }

        return $this->find($id);
    }

    public function delete(int $id, bool $force = false): bool
    {
        $model = $this->model;

        if ($model::softDeletes() && !$force) {
            if ($this->find($id) === null) {
                return false;
            }

            $this->database->execute(
                sprintf('UPDATE `%s` SET deleted_at = NOW() WHERE id = :id', $model::table()),
                ['id' => $id],
            );

            return true;
        }

        return $this->database->execute(
            sprintf('DELETE FROM `%s` WHERE id = :id', $model::table()),
            ['id' => $id],
        ) > 0;
    }

    public function restore(int $id): ?array
    {
        $model = $this->model;

        if (!$model::softDeletes() || $this->find($id, [], true) === null) {
            return null;
        }

        $this->database->execute(
            sprintf('UPDATE `%s` SET deleted_at = NULL WHERE id = :id', $model::table()),
            ['id' => $id],
        );

        return $this->find($id);
    }

    /**
     * Menambahkan relasi yang diminta lewat ?with=user,comments
     *
     * @param array<string, mixed> $row
     * @param array<int, string> $with
     * @return array<string, mixed>
     */
    private function attach(array $row, array $with): array
    {
        if ($with === []) {
            return $row;
        }

        $relations = $this->model::relations();

        foreach ($with as $name) {
            if (!isset($relations[$name])) {
                continue;
            }

            $relation = $relations[$name];
            /** @var class-string<\App\Core\Model> $related */
            $related = (string) $relation['model'];
            $foreignKey = (string) $relation['foreignKey'];
            $type = (string) ($relation['type'] ?? 'belongsTo');

            if ($type === 'hasMany') {
                $rows = $this->database->select(
                    sprintf('SELECT * FROM `%s` WHERE `%s` = :id', $related::table(), $foreignKey),
                    ['id' => $row['id']],
                );

                $row[$name] = array_map(static fn (array $item): array => $related::serialize($item), $rows);
                continue;
            }

            $value = $row[$foreignKey] ?? null;

            if ($value === null || $value === '') {
                $row[$name] = null;
                continue;
            }

            $item = $this->database->first(
                sprintf('SELECT * FROM `%s` WHERE id = :id', $related::table()),
                ['id' => $value],
            );

            $row[$name] = $item === null ? null : $related::serialize($item);
        }

        return $row;
    }

    /**
     * @param array<string, string> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    /**
     * @param array<string, string> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function where(array $filters, bool $publishedOnly = false): array
    {
        $model = $this->model;
        $clauses = [];
        $params = [];

        if ($publishedOnly && in_array('status', $model::fieldNames(), true)) {
            unset($filters['status']);
            $clauses[] = "`status` = 'published'";

            if (in_array('published_at', $model::fieldNames(), true)) {
                $clauses[] = '(published_at IS NULL OR published_at <= NOW())';
            }
        }

        foreach ($filters as $field => $value) {
            if (!in_array($field, $model::fieldNames(), true) || $value === '') {
                continue;
            }

            $alias = 'f_' . $field;
            $clauses[] = sprintf('`%s` = :%s', $field, $alias);
            $params[$alias] = $value;
        }

        if ($this->model::softDeletes()) {
            $clauses[] = 'deleted_at IS NULL';
        }

        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * Validasi otomatis dari metadata Model.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function validated(array $input, ?int $id = null): array
    {
        $data = [];
        $fields = $this->model::fields();

        foreach ($fields as $name => $field) {
            $label = (string) ($field['label'] ?? $name);
            $type = (string) ($field['type'] ?? 'string');
            $required = ($field['required'] ?? false) === true;
            $exists = array_key_exists($name, $input);

            if (!$exists) {
                if ($required && $id === null) {
                    throw new HttpException(422, 'VALIDATION_ERROR', $label . ' wajib diisi');
                }

                continue;
            }

            $value = $input[$name];

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null) {
                if ($required) {
                    throw new HttpException(422, 'VALIDATION_ERROR', $label . ' wajib diisi');
                }

                $data[$name] = null;
                continue;
            }

            if ($type === 'string' || $type === 'text') {
                if (!in_array(gettype($value), ['string', 'integer', 'double'], true)) {
                    throw new HttpException(422, 'VALIDATION_ERROR', $label . ' harus berupa teks');
                }

                $value = (string) $value;
                $max = (int) ($field['max'] ?? 0);

                if ($max > 0 && mb_strlen($value) > $max) {
                    throw new HttpException(422, 'VALIDATION_ERROR', sprintf('%s maksimal %d karakter', $label, $max));
                }
            }

            if ($type === 'int' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                throw new HttpException(422, 'VALIDATION_ERROR', $label . ' harus berupa angka');
            }

            if ($type === 'decimal' && !is_numeric($value)) {
                throw new HttpException(422, 'VALIDATION_ERROR', $label . ' harus berupa angka');
            }

            $value = $this->model::castValue($name, $value);

            if (($field['unique'] ?? false) === true) {
                $this->assertUnique($name, $value, $id, $label);
            }

            $data[$name] = $value;
        }

        $this->applyRules($data, $fields);

        return $data;
    }

    /**
     * Menerapkan aturan tambahan (field 'rules') lewat Validator terpusat.
     *
     * @param array<string, mixed> $data
     * @param array<string, array<string, mixed>> $fields
     */
    private function applyRules(array &$data, array $fields): void
    {
        foreach ($fields as $name => $field) {
            if (!array_key_exists('rules', $field)) {
                continue;
            }

            $label = (string) ($field['label'] ?? $name);
            $message = Validator::check($data[$name] ?? null, $field['rules'], $data);

            if ($message !== null) {
                throw new HttpException(422, 'VALIDATION_ERROR', $label . ' ' . $message);
            }
        }
    }

    /**
     * Mengisi slug kosong dari judul. Hanya untuk Model yang punya field slug dan title.
     */
    public function ensureSlugs(): int
    {
        if (!isset($this->model::fields()['slug']) || !in_array('title', $this->model::fieldNames(), true)) {
            return 0;
        }

        $rows = $this->database->select(
            sprintf('SELECT id, title FROM `%s` WHERE slug IS NULL OR slug = \'\'', $this->model::table()),
        );
        $filled = 0;

        foreach ($rows as $row) {
            $slug = $this->uniqueSlug($this->slugify((string) ($row['title'] ?? '')), (int) $row['id']);
            $this->database->execute(
                sprintf('UPDATE `%s` SET slug = :slug WHERE id = :id', $this->model::table()),
                ['slug' => $slug, 'id' => $row['id']],
            );
            $filled++;
        }

        return $filled;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function withSlug(array $data, array $input, ?int $id): array
    {
        if (!isset($this->model::fields()['slug'])) {
            return $data;
        }

        $incoming = $data['slug'] ?? null;

        if (is_string($incoming) && $incoming !== '') {
            $data['slug'] = $this->uniqueSlug($this->slugify($incoming), $id);

            return $data;
        }

        if ($id !== null && !array_key_exists('slug', $input)) {
            unset($data['slug']);

            return $data;
        }

        $title = isset($data['title']) ? (string) $data['title'] : '';

        if ($title === '' && $id !== null) {
            $current = $this->find($id);
            $title = (string) ($current['title'] ?? '');
        }

        $data['slug'] = $this->uniqueSlug($this->slugify($title), $id);

        return $data;
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ō' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
        ]);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
        $value = trim($value, '-');

        if ($value === '' || preg_match('/^\d+$/', $value) === 1) {
            $value = ($value === '' ? 'item' : $value . '-item');
        }

        return mb_substr($value, 0, 180);
    }

    private function uniqueSlug(string $base, ?int $id): string
    {
        $candidate = $base;
        $number = 2;

        while ($this->slugTaken($candidate, $id) || $this->slugReserved($candidate)) {
            $suffix = '-' . $number;
            $candidate = mb_substr($base, 0, 180 - strlen($suffix)) . $suffix;
            $number++;

            if ($number > 100) {
                break;
            }
        }

        return $candidate;
    }

    private function slugTaken(string $slug, ?int $id): bool
    {
        $sql = sprintf('SELECT id FROM `%s` WHERE slug = :slug', $this->model::table())
            . ($id === null ? '' : ' AND id <> :id')
            . ' LIMIT 1';
        $params = ['slug' => $slug];

        if ($id !== null) {
            $params['id'] = $id;
        }

        return $this->database->first($sql, $params) !== null;
    }

    private function slugReserved(string $slug): bool
    {
        if (in_array($slug, ['api', 'assets', 'dashboard', 'vendor', 'deps', 'theme'], true)) {
            return true;
        }

        $root = dirname(__DIR__, 3) . '/public';

        return is_file($root . '/theme/' . $slug . '.html') || is_file($root . '/' . $slug . '.html');
    }

    private function assertUnique(string $field, mixed $value, ?int $id, string $label): void
    {
        $sql = sprintf('SELECT id FROM `%s` WHERE `%s` = :%s', $this->model::table(), $field, $field)
            . ($id === null ? '' : ' AND id <> :id')
            . ' LIMIT 1';

        $params = [$field => $value];

        if ($id !== null) {
            $params['id'] = $id;
        }

        if ($this->database->first($sql, $params) !== null) {
            throw new HttpException(422, 'DUPLICATE_VALUE', $label . ' sudah dipakai');
        }
    }

    private function rethrowDuplicate(\PDOException $exception): void
    {
        if ($exception->getCode() !== '23000') {
            return;
        }

        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        throw match ($driverCode) {
            1062 => new HttpException(422, 'DUPLICATE_VALUE', 'Nilai sudah dipakai'),
            1452 => new HttpException(422, 'INVALID_RELATION', 'Data relasi tidak ditemukan'),
            1451 => new HttpException(409, 'RELATION_CONFLICT', 'Data masih dipakai oleh tabel lain'),
            default => new HttpException(409, 'CONSTRAINT_VIOLATION', 'Data melanggar aturan database'),
        };
    }
}