<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

/**
 * Файл миграции, разобранный на отдельные выражения.
 *
 * Разбор простой: убираются строчные комментарии, текст делится по точке
 * с запятой. Для DDL этого достаточно. Настоящий парсер понадобился бы,
 * появись в миграциях строковые литералы с точкой с запятой или хранимые
 * процедуры, — тогда лучше не усложнять это место, а перейти на готовый
 * инструмент миграций.
 */
final readonly class SqlScript
{
    private function __construct(public string $sql)
    {
    }

    public static function fromString(string $sql): self
    {
        return new self($sql);
    }

    public static function fromFile(string $path): self
    {
        $sql = @file_get_contents($path);

        if (false === $sql) {
            throw new \RuntimeException(sprintf('Не удалось прочитать файл миграции %s.', $path));
        }

        return new self($sql);
    }

    /**
     * @return list<string>
     */
    public function statements(): array
    {
        $withoutComments = (string) preg_replace('/^\s*--.*$/m', '', $this->sql);

        $statements = [];

        foreach (explode(';', $withoutComments) as $statement) {
            $statement = trim($statement);

            if ('' !== $statement) {
                $statements[] = $statement;
            }
        }

        return $statements;
    }
}
