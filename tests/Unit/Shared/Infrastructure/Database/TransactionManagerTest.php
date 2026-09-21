<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Database;

use App\Shared\Infrastructure\Database\TransactionManager;
use App\Tests\Support\RecordingPdo;
use PHPUnit\Framework\TestCase;

final class TransactionManagerTest extends TestCase
{
    private RecordingPdo $connection;

    private TransactionManager $transactions;

    protected function setUp(): void
    {
        $this->connection = new RecordingPdo();
        $this->transactions = new TransactionManager($this->connection);
    }

    public function testCommitsOnSuccess(): void
    {
        $result = $this->transactions->transactional(static fn (): string => 'готово');

        self::assertSame('готово', $result);
        self::assertSame(['begin', 'commit'], $this->connection->calls);
    }

    public function testRollsBackAndRethrows(): void
    {
        try {
            $this->transactions->transactional(static function (): void {
                throw new \RuntimeException('подписчик упал');
            });
            self::fail('Исключение должно пробрасываться дальше');
        } catch (\RuntimeException $e) {
            self::assertSame('подписчик упал', $e->getMessage());
        }

        self::assertSame(['begin', 'rollback'], $this->connection->calls);
    }

    public function testNestedCallJoinsOuterTransaction(): void
    {
        $this->transactions->transactional(function (): void {
            $this->transactions->transactional(static fn (): null => null);
        });

        self::assertSame(
            ['begin', 'commit'],
            $this->connection->calls,
            'Вложенный вызов не должен открывать вторую транзакцию',
        );
    }

    public function testFailureInsideNestedCallRollsBackOnce(): void
    {
        try {
            $this->transactions->transactional(function (): void {
                $this->transactions->transactional(static function (): void {
                    throw new \RuntimeException('внутри');
                });
            });
        } catch (\RuntimeException) {
            // ожидаемо
        }

        self::assertSame(['begin', 'rollback'], $this->connection->calls);
    }

    public function testManagerIsReusableAfterFailure(): void
    {
        try {
            $this->transactions->transactional(static function (): void {
                throw new \RuntimeException('первая');
            });
        } catch (\RuntimeException) {
            // ожидаемо
        }

        $this->transactions->transactional(static fn (): null => null);

        self::assertSame(
            ['begin', 'rollback', 'begin', 'commit'],
            $this->connection->calls,
            'Счётчик глубины должен обнуляться после отката',
        );
    }
}
