<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Bus\DeferredCommandBus;
use App\Shared\Infrastructure\Container\Container;
use App\Shared\Infrastructure\Http\Kernel;
use App\Shared\Infrastructure\Http\PageNotFound;
use App\Shared\Infrastructure\Http\Request;
use App\Shared\Infrastructure\Http\Response;
use App\Shared\Infrastructure\Http\Router;
use App\Tests\Support\ArrayTemplateRenderer;
use App\Tests\Support\RecordingLogger;
use PHPUnit\Framework\TestCase;

final class KernelTest extends TestCase
{
    private Container $container;

    private Router $router;

    private RecordingLogger $logger;

    private DeferredCommandBus $deferred;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->router = new Router();
        $this->logger = new RecordingLogger();
        $this->deferred = new DeferredCommandBus($this->logger);
    }

    public function testCallsControllerAndReturnsItsResponse(): void
    {
        $this->route('/', static fn (): Response => Response::text('главная'));

        $response = $this->kernel()->handle(new Request('GET', '/'));

        self::assertSame(200, $response->status);
        self::assertSame('главная', $response->body);
    }

    public function testPassesRouteParametersToController(): void
    {
        $this->route('/category/{slug}', static fn (Request $r): Response => Response::text((string) $r->attribute('slug')));

        self::assertSame('novosti', $this->kernel()->handle(new Request('GET', '/category/novosti'))->body);
    }

    public function testUnknownRouteGives404(): void
    {
        $response = $this->kernel()->handle(new Request('GET', '/net-takoy'));

        self::assertSame(404, $response->status);
        self::assertSame([], $this->logger->records, 'Отсутствие страницы не повод писать в лог');
    }

    public function testPageNotFoundFromControllerGives404(): void
    {
        $this->route('/posts/{slug}', static function (): never {
            throw new PageNotFound();
        });

        $response = $this->kernel()->handle(new Request('GET', '/posts/net'));

        self::assertSame(404, $response->status);
        self::assertSame([], $this->logger->records);
    }

    public function testControllerFailureGives500AndIsLogged(): void
    {
        $this->route('/', static function (): never {
            throw new \RuntimeException('база недоступна');
        });

        $response = $this->kernel()->handle(new Request('GET', '/'));

        self::assertSame(500, $response->status);
        self::assertCount(1, $this->logger->records);
        self::assertSame('error', $this->logger->records[0]['level']);
        self::assertStringContainsString('база недоступна', $this->logger->records[0]['message']);
    }

    public function testProductionPageHidesDetails(): void
    {
        $this->route('/', static function (): never {
            throw new \RuntimeException('пароль от базы в сообщении');
        });

        $body = $this->kernel(debug: false)->handle(new Request('GET', '/'))->body;

        self::assertStringNotContainsString('пароль от базы', $body);
    }

    public function testDebugPageShowsDetails(): void
    {
        $this->route('/', static function (): never {
            throw new \RuntimeException('подробности для разработчика');
        });

        $body = $this->kernel(debug: true)->handle(new Request('GET', '/'))->body;

        self::assertStringContainsString('подробности для разработчика', $body);
    }

    public function testControllerReturningGarbageIsReported(): void
    {
        $this->route('/', static fn (): string => 'не ответ');

        $response = $this->kernel()->handle(new Request('GET', '/'));

        self::assertSame(500, $response->status);
        self::assertStringContainsString('не вернул ответ', $this->logger->records[0]['message']);
    }

    private function route(string $pattern, callable $controller): void
    {
        $id = 'controller.' . md5($pattern);

        $this->router->get($pattern, $id);
        $this->container->set($id, static fn (): callable => $controller);
    }

    public function testDeferredWorkWaitsForTerminate(): void
    {
        $done = [];
        $this->deferred->register(DeferredProbe::class, static function () use (&$done): void {
            $done[] = 'выполнено';
        });

        $this->route('/', function (): Response {
            $this->deferred->push(new DeferredProbe());

            return Response::text('страница');
        });

        $kernel = $this->kernel();
        $response = $kernel->handle(new Request('GET', '/'));

        self::assertSame('страница', $response->body);
        self::assertSame([], $done, 'До terminate отложенная работа не должна выполняться');

        $kernel->terminate();

        self::assertSame(['выполнено'], $done);
    }

    public function testTerminateSwallowsFailures(): void
    {
        $this->deferred->register(DeferredProbe::class, static function (): never {
            throw new \RuntimeException('счётчик не обновился');
        });

        $this->route('/', function (): Response {
            $this->deferred->push(new DeferredProbe());

            return Response::text('страница');
        });

        $kernel = $this->kernel();
        $kernel->handle(new Request('GET', '/'));
        $kernel->terminate();

        self::assertCount(1, $this->logger->records, 'Сбой должен попасть в лог, а не наружу');
    }

    private function kernel(bool $debug = false): Kernel
    {
        return new Kernel(
            $this->container,
            $this->router,
            $this->deferred,
            new ArrayTemplateRenderer(),
            $this->logger,
            $debug,
        );
    }
}

final class DeferredProbe
{
}
