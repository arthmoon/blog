<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testReadsQueryParameter(): void
    {
        $request = new Request('GET', '/category/novosti', ['sort' => 'views']);

        self::assertSame('views', $request->query('sort'));
        self::assertNull($request->query('page'));
    }

    /**
     * @return iterable<string, array{?string, int}>
     */
    public static function pageValues(): iterable
    {
        yield 'число' => ['3', 3];
        yield 'отрицательное' => ['-2', -2];
        yield 'параметра нет' => [null, 1];
        yield 'пустая строка' => ['', 1];
        yield 'текст' => ['abc', 1];
        yield 'дробное' => ['2.5', 1];
        yield 'инъекция' => ['1; DROP TABLE posts', 1];
    }

    #[DataProvider('pageValues')]
    public function testQueryIntFallsBackOnGarbage(?string $value, int $expected): void
    {
        $request = new Request('GET', '/', null === $value ? [] : ['page' => $value]);

        self::assertSame($expected, $request->queryInt('page', 1));
    }

    public function testAttributesComeFromRouter(): void
    {
        $request = (new Request('GET', '/category/novosti'))->withAttributes(['slug' => 'novosti']);

        self::assertSame('novosti', $request->attribute('slug'));
        self::assertNull($request->attribute('other'));
    }

    public function testWithAttributesKeepsQueryAndPath(): void
    {
        $request = (new Request('GET', '/category/novosti', ['sort' => 'views']))
            ->withAttributes(['slug' => 'novosti']);

        self::assertSame('/category/novosti', $request->path);
        self::assertSame('views', $request->query('sort'));
    }

    public function testOriginalRequestIsNotModified(): void
    {
        $request = new Request('GET', '/');
        $request->withAttributes(['slug' => 'novosti']);

        self::assertNull($request->attribute('slug'));
    }
}
