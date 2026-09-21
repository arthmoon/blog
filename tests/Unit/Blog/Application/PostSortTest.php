<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Application;

use App\Blog\Application\PostSort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PostSortTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, PostSort}>
     */
    public static function queryValues(): iterable
    {
        yield 'по дате' => ['date', PostSort::Newest];
        yield 'по просмотрам' => ['views', PostSort::Popular];
        yield 'параметра нет' => [null, PostSort::Newest];
        yield 'пустая строка' => ['', PostSort::Newest];
        yield 'неизвестное значение' => ['rating', PostSort::Newest];
        yield 'попытка инъекции' => ['views; DROP TABLE posts', PostSort::Newest];
        yield 'регистр важен' => ['VIEWS', PostSort::Newest];
    }

    #[DataProvider('queryValues')]
    public function testResolvesQueryStringValue(?string $value, PostSort $expected): void
    {
        self::assertSame($expected, PostSort::fromQueryString($value));
    }

    public function testDefaultIsNewest(): void
    {
        self::assertSame(PostSort::Newest, PostSort::default());
    }

    public function testOnlyTwoOrdersAreAllowed(): void
    {
        self::assertSame(['date', 'views'], array_map(
            static fn (PostSort $sort): string => $sort->value,
            PostSort::cases(),
        ));
    }
}
