<?php

declare(strict_types=1);

namespace App\Core;

final class Database
{
    private ?\PDO $pdo = null;

    /**
     * @param array{host: string, port: int, database: string, username: string, password: string, charset: string} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public static function fromConfigFile(string $file): self
    {
        return new self(require $file);
    }

    public function pdo(): \PDO
    {
        if ($this->pdo instanceof \PDO) {
            return $this->pdo;
        }

        $config = $this->config;

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'],
        );

        $this->pdo = new \PDO($dsn, $config['username'], $config['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $this->pdo;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        return $this->rows($sql, $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function first(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }

    /**
     * Menjalankan beberapa query dalam satu transaksi.
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback($this);
            $pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $sql, array $params): array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }
}