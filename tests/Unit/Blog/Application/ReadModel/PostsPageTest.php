<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Application\ReadModel;

use App\Blog\Application\ReadModel\PostsPage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PostsPageTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function pageCounts(): iterable
    {
        yield 'ровное деление' => [24, 12, 2];
        yield 'остаток даёт ещё страницу' => [25, 12, 3];
        yield 'меньше одной страницы' => [5, 12, 1];
        yield 'ровно одна' => [12, 12, 1];
        yield 'пусто — всё равно одна' => [0, 12, 1];
    }

    #[DataProvider('pageCounts')]
    public function testPageCount(int $total, int $perPage, int $expected): void
    {
        self::assertSame($expected, PostsPage::create([], $total, 1, $perPage)->pageCount());
    }

    public function testOffsetGrowsWithPage(): void
    {
        self::assertSame(0, PostsPage::create([], 100, 1, 12)->offset());
        self::assertSame(12, PostsPage::create([], 100, 2, 12)->offset());
        self::assertSame(48, PostsPage::create([], 100, 5, 12)->offset());
    }

    public function testNavigationFlagsOnFirstPage(): void
    {
        $page = PostsPage::create([], 25, 1, 12);

        self::assertFalse($page->hasPrevious());
        self::assertTrue($page->hasNext());
    }

    public function testNavigationFlagsOnLastPage(): void
    {
        $page = PostsPage::create([], 25, 3, 12);

        self::assertTrue($page->hasPrevious());
        self::assertFalse($page->hasNext());
    }

    public function testEmptyPageIsNotOutOfRange(): void
    {
        $page = PostsPage::empty(1, 12);

        self::assertTrue($page->isEmpty());
        self::assertFalse($page->isOutOfRange(), 'Пустая категория — это первая страница, а не 404');
    }

    public function testPageBeyondLastIsOutOfRange(): void
    {
        self::assertTrue(PostsPage::create([], 25, 4, 12)->isOutOfRange());
        self::assertFalse(PostsPage::create([], 25, 3, 12)->isOutOfRange());
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function invalidArguments(): iterable
    {
        yield 'нулевая страница' => [0, 0, 12];
        yield 'отрицательная страница' => [0, -1, 12];
        yield 'нулевой размер' => [0, 1, 0];
        yield 'отрицательное количество' => [-1, 1, 12];
    }

    #[DataProvider('invalidArguments')]
    public function testRejectsInvalidArguments(int $total, int $page, int $perPage): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PostsPage::create([], $total, $page, $perPage);
    }
}
