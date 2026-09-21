<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Подделка PDO для проверки управления транзакциями.
 *
 * Родительский конструктор не вызывается — соединение не нужно, а все
 * используемые методы переопределены и до внутренностей PDO не доходят.
 */
final class RecordingPdo extends \PDO
{
    /** @var list<string> */
    public array $calls = [];

    public bool $failOnCommit = false;

    public function __construct()
    {
    }

    public function beginTransaction(): bool
    {
        $this->calls[] = 'begin';

        return true;
    }

    public function commit(): bool
    {
        $this->calls[] = 'commit';

        if ($this->failOnCommit) {
            throw new \PDOException('коммит не прошёл');
        }

        return true;
    }

    public function rollBack(): bool
    {
        $this->calls[] = 'rollback';

        return true;
    }
}
