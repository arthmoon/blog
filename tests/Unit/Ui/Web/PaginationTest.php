<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ui\Web;

use App\Ui\Web\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, list<int|null>}>
     */
    public static function windows(): iterable
    {
        yield 'одна страница' => [1, 1, [1]];
        yield 'три страницы целиком' => [2, 3, [1, 2, 3]];
        yield 'семь страниц без разрывов' => [4, 7, [1, 2, 3, 4, 5, 6, 7]];
        yield 'начало длинного списка' => [1, 20, [1, 2, 3, null, 20]];
        yield 'середина длинного списка' => [10, 20, [1, null, 8, 9, 10, 11, 12, null, 20]];
        yield 'конец длинного списка' => [20, 20, [1, null, 18, 19, 20]];
        // Оба разрыва здесь по одной странице, и оба разворачиваются:
        // «1 … 3» занимает столько же места, сколько «1 2 3».
        yield 'разрывы в одну страницу разворачиваются' => [5, 9, [1, 2, 3, 4, 5, 6, 7, 8, 9]];
        yield 'короткий разрыв развернулся, длинный остался' => [4, 12, [1, 2, 3, 4, 5, 6, null, 12]];
        yield 'разрыв ровно в одну справа' => [4, 8, [1, 2, 3, 4, 5, 6, 7, 8]];
    }

    /**
     * @param list<int|null> $expected
     */
    #[DataProvider('windows')]
    public function testBuildsWindow(int $current, int $pageCount, array $expected): void
    {
        self::assertSame($expected, Pagination::windowed($current, $pageCount));
    }

    public function testAlwaysContainsFirstAndLast(): void
    {
        $window = Pagination::windowed(10, 30);

        self::assertSame(1, $window[0]);
        self::assertSame(30, $window[array_key_last($window)]);
    }

    public function testNeverRepeatsPageNumbers(): void
    {
        $numbers = array_filter(Pagination::windowed(3, 12), static fn (?int $n): bool => null !== $n);

        self::assertSame(array_values($numbers), array_values(array_unique($numbers)));
    }

    public function testRadiusIsConfigurable(): void
    {
        self::assertSame([1, null, 9, 10, 11, null, 20], Pagination::windowed(10, 20, radius: 1));
    }

    public function testRejectsEmptyPageCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Pagination::windowed(1, 0);
    }
}
