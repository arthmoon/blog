<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus;

use App\Shared\Infrastructure\Bus\DeferredCommandBus;
use App\Tests\Support\RecordingLogger;
use PHPUnit\Framework\TestCase;

final class DeferredCommandBusTest extends TestCase
{
    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    public function testPushDoesNotExecuteUntilFlush(): void
    {
        $executed = [];
        $bus = new DeferredCommandBus($this->logger);
        $bus->register(SampleCommand::class, static function (SampleCommand $c) use (&$executed): void {
            $executed[] = $c->name;
        });

        $bus->push(new SampleCommand('первая'));

        self::assertSame([], $executed, 'До flush обработчик не должен запускаться');
        self::assertSame(1, $bus->pending());

        $bus->flush();

        self::assertSame(['первая'], $executed);
    }

    public function testFlushPreservesOrderAndEmptiesQueue(): void
    {
        $executed = [];
        $bus = new DeferredCommandBus($this->logger);
        $bus->register(SampleCommand::class, static function (SampleCommand $c) use (&$executed): void {
            $executed[] = $c->name;
        });

        $bus->push(new SampleCommand('раз'));
        $bus->push(new SampleCommand('два'));
        $bus->flush();
        $bus->flush();

        self::assertSame(['раз', 'два'], $executed, 'Повторный flush не должен выполнять их заново');
        self::assertSame(0, $bus->pending());
    }

    public function testSynchronousModeExecutesImmediately(): void
    {
        $executed = [];
        $bus = new DeferredCommandBus($this->logger, deferred: false);
        $bus->register(SampleCommand::class, static function (SampleCommand $c) use (&$executed): void {
            $executed[] = $c->name;
        });

        $bus->push(new SampleCommand('сразу'));

        self::assertSame(['сразу'], $executed);
        self::assertSame(0, $bus->pending());
    }

    public function testMissingHandlerIsReportedAtPushTime(): void
    {
        $bus = new DeferredCommandBus($this->logger);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/не зарегистрирован обработчик/');

        $bus->push(new SampleCommand('без обработчика'));
    }

    public function testHandlerFailureIsLoggedAndDoesNotEscape(): void
    {
        $bus = new DeferredCommandBus($this->logger);
        $bus->register(SampleCommand::class, static function (): never {
            throw new \RuntimeException('база недоступна');
        });

        $bus->push(new SampleCommand('упадёт'));
        $bus->flush();

        self::assertCount(1, $this->logger->records);
        self::assertSame('error', $this->logger->records[0]['level']);
        self::assertStringContainsString('база недоступна', $this->logger->records[0]['message']);
    }

    public function testOneFailureDoesNotStopTheRest(): void
    {
        $executed = [];
        $bus = new DeferredCommandBus($this->logger);
        $bus->register(SampleCommand::class, static function (SampleCommand $c) use (&$executed): void {
            if ('упадёт' === $c->name) {
                throw new \RuntimeException('сбой');
            }

            $executed[] = $c->name;
        });

        $bus->push(new SampleCommand('упадёт'));
        $bus->push(new SampleCommand('доедет'));
        $bus->flush();

        self::assertSame(['доедет'], $executed);
        self::assertCount(1, $this->logger->records);
    }
}

final class SampleCommand
{
    public function __construct(public string $name)
    {
    }
}
