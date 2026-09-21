<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\Request;
use App\Shared\Infrastructure\Http\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/', 'HomeController');
        $this->router->get('/category/{slug}', 'CategoryController');
        $this->router->get('/posts/{slug}', 'PostController');
    }

    public function testMatchesRoot(): void
    {
        $route = $this->router->match(new Request('GET', '/'));

        self::assertNotNull($route);
        self::assertSame('HomeController', $route->handler);
        self::assertSame([], $route->parameters);
    }

    public function testExtractsParameter(): void
    {
        $route = $this->router->match(new Request('GET', '/category/novosti'));

        self::assertNotNull($route);
        self::assertSame('CategoryController', $route->handler);
        self::assertSame(['slug' => 'novosti'], $route->parameters);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function equivalentPaths(): iterable
    {
        yield 'без слеша' => ['/category/novosti'];
        yield 'со слешем' => ['/category/novosti/'];
        yield 'с несколькими слешами' => ['/category/novosti///'];
    }

    #[DataProvider('equivalentPaths')]
    public function testTrailingSlashesAreIgnored(string $path): void
    {
        $route = $this->router->match(new Request('GET', $path));

        self::assertNotNull($route);
        self::assertSame(['slug' => 'novosti'], $route->parameters);
    }

    public function testParameterDoesNotSwallowSlash(): void
    {
        self::assertNull(
            $this->router->match(new Request('GET', '/posts/lishniy/uroven')),
            'Параметр не должен захватывать вложенный путь',
        );
    }

    public function testUnknownPathGivesNull(): void
    {
        self::assertNull($this->router->match(new Request('GET', '/net-takoy-stranicy')));
    }

    public function testEmptyParameterDoesNotMatch(): void
    {
        self::assertNull($this->router->match(new Request('GET', '/category/')));
    }

    public function testOtherMethodDoesNotMatch(): void
    {
        self::assertNull($this->router->match(new Request('POST', '/')));
    }

    public function testMethodComparisonIsCaseInsensitive(): void
    {
        self::assertNotNull($this->router->match(new Request('get', '/')));
    }

    public function testLiteralPartIsEscaped(): void
    {
        $router = new Router();
        $router->get('/feed.xml', 'FeedController');

        self::assertNotNull($router->match(new Request('GET', '/feed.xml')));
        self::assertNull(
            $router->match(new Request('GET', '/feedaxml')),
            'Точка в шаблоне должна быть точкой, а не «любым символом»',
        );
    }

    public function testFirstMatchingRouteWins(): void
    {
        $router = new Router();
        $router->get('/posts/{slug}', 'FirstController');
        $router->get('/posts/{other}', 'SecondController');

        self::assertSame('FirstController', $router->match(new Request('GET', '/posts/a'))?->handler);
    }
}
