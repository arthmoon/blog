<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Container;

use App\Shared\Infrastructure\Container\CircularDependency;
use App\Shared\Infrastructure\Container\Container;
use App\Shared\Infrastructure\Container\ServiceNotFound;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function testResolvesRegisteredService(): void
    {
        $container = new Container();
        $container->set('greeting', static fn (): string => 'привет');

        self::assertSame('привет', $container->get('greeting'));
    }

    public function testFactoryRunsOnceAndResultIsReused(): void
    {
        $calls = 0;
        $container = new Container();
        $container->set('service', static function () use (&$calls): object {
            ++$calls;

            return new \stdClass();
        });

        $first = $container->get('service');
        $second = $container->get('service');

        self::assertSame(1, $calls, 'Фабрика не должна вызываться повторно');
        self::assertSame($first, $second);
    }

    public function testFactoryIsLazy(): void
    {
        $called = false;
        $container = new Container();
        $container->set('service', static function () use (&$called): string {
            $called = true;

            return 'значение';
        });

        self::assertFalse($called, 'Регистрация не должна создавать сервис');
    }

    public function testServiceCanDependOnAnother(): void
    {
        $container = new Container();
        $container->set('inner', static fn (): string => 'ядро');
        $container->set('outer', static fn (Container $c): string => 'обёртка вокруг ' . $c->get('inner'));

        self::assertSame('обёртка вокруг ядро', $container->get('outer'));
    }

    public function testUnknownServiceIsReported(): void
    {
        $this->expectException(ServiceNotFound::class);
        $this->expectExceptionMessageMatches('/неизвестный/');

        (new Container())->get('неизвестный');
    }

    public function testCircularDependencyIsReported(): void
    {
        $container = new Container();
        $container->set('a', static fn (Container $c): mixed => $c->get('b'));
        $container->set('b', static fn (Container $c): mixed => $c->get('a'));

        $this->expectException(CircularDependency::class);
        $this->expectExceptionMessageMatches('/a -> b -> a/');

        $container->get('a');
    }

    public function testContainerStaysUsableAfterFailedResolution(): void
    {
        $container = new Container();
        $container->set('падает', static function (): never {
            throw new \RuntimeException('сломалось');
        });
        $container->set('рабочий', static fn (): string => 'ок');

        try {
            $container->get('падает');
        } catch (\RuntimeException) {
            // ожидаемо
        }

        self::assertSame('ок', $container->get('рабочий'), 'Цепочка сборки должна очищаться');
    }

    public function testHasReportsRegistration(): void
    {
        $container = new Container();
        $container->set('есть', static fn (): string => 'да');

        self::assertTrue($container->has('есть'));
        self::assertFalse($container->has('нет'));
    }

    public function testNullValueIsCached(): void
    {
        $calls = 0;
        $container = new Container();
        $container->set('пусто', static function () use (&$calls): null {
            ++$calls;

            return null;
        });

        $container->get('пусто');
        $container->get('пусто');

        self::assertSame(1, $calls, 'null — тоже значение, пересоздавать его не нужно');
    }
}
