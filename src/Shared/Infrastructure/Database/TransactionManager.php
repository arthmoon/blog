<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

/**
 * Выполнение операции в транзакции.
 *
 * Вложенные вызовы присоединяются к внешней транзакции по счётчику глубины,
 * а не открывают новую: в MySQL вложенных транзакций нет, второй
 * beginTransaction() молча закоммитил бы первую. Точки сохранения дали бы
 * настоящую вложенность, но частичный откат здесь никому не нужен, а
 * поведение усложнилось бы.
 */
final class TransactionManager
{
    private int $depth = 0;

    public function __construct(private readonly \PDO $connection)
    {
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed
    {
        if (0 === $this->depth) {
            $this->connection->beginTransaction();
        }

        ++$this->depth;

        try {
            $result = $operation();
        } catch (\Throwable $e) {
            --$this->depth;

            if (0 === $this->depth) {
                $this->connection->rollBack();
            }

            throw $e;
        }

        --$this->depth;

        if (0 === $this->depth) {
            $this->connection->commit();
        }

        return $result;
    }
}
