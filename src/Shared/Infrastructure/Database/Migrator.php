<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

/**
 * Простой раннер миграций: складывает применённые версии в таблицу и
 * прогоняет только новые файлы по возрастанию имени.
 *
 * Транзакции вокруг файла нет намеренно. В MySQL операции DDL вызывают
 * неявный коммит, так что откатить CREATE TABLE всё равно не получится,
 * и обёртка создавала бы ложное чувство безопасности. Версия отмечается
 * применённой только после того, как файл отработал целиком: упавшую
 * миграцию придётся доводить руками, и лучше знать об этом заранее.
 */
final readonly class Migrator
{
    private const string REGISTRY_TABLE = 'migrations';

    public function __construct(
        private \PDO $connection,
        private string $migrationsPath,
    ) {
    }

    /**
     * Применяет невыполненные миграции.
     *
     * @return list<string> версии, применённые в этом запуске
     */
    public function migrate(): array
    {
        $this->ensureRegistryTable();

        $applied = $this->appliedVersions();
        $executed = [];

        foreach ($this->availableVersions() as $version) {
            if (\in_array($version, $applied, true)) {
                continue;
            }

            $this->runFile($version);
            $this->markApplied($version);

            $executed[] = $version;
        }

        return $executed;
    }

    /**
     * @return list<string>
     */
    public function appliedVersions(): array
    {
        $this->ensureRegistryTable();

        $sql = sprintf('SELECT version FROM %s ORDER BY version', self::REGISTRY_TABLE);

        /** @var list<string> $versions */
        $versions = $this->connection->query($sql)?->fetchAll(\PDO::FETCH_COLUMN) ?: [];

        return $versions;
    }

    /**
     * @return list<string>
     */
    public function availableVersions(): array
    {
        $files = glob($this->migrationsPath . '/*.sql');

        if (false === $files) {
            return [];
        }

        $versions = array_map(static fn (string $file): string => basename($file, '.sql'), $files);
        sort($versions);

        return $versions;
    }

    /**
     * @return list<string> версии, которые ещё не применены
     */
    public function pendingVersions(): array
    {
        $applied = $this->appliedVersions();

        return array_values(array_filter(
            $this->availableVersions(),
            static fn (string $version): bool => !\in_array($version, $applied, true),
        ));
    }

    private function runFile(string $version): void
    {
        $script = SqlScript::fromFile(sprintf('%s/%s.sql', $this->migrationsPath, $version));

        foreach ($script->statements() as $statement) {
            $this->connection->exec($statement);
        }
    }

    private function markApplied(string $version): void
    {
        $sql = sprintf('INSERT INTO %s (version, applied_at) VALUES (?, NOW())', self::REGISTRY_TABLE);

        $this->connection->prepare($sql)->execute([$version]);
    }

    private function ensureRegistryTable(): void
    {
        $this->connection->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                version VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            self::REGISTRY_TABLE,
        ));
    }
}
