<?php

declare(strict_types=1);

namespace App\Tests\Integration\Web;

use App\Tests\Integration\WebTestCase;

final class CategoryPageTest extends WebTestCase
{
    public function testShowsTitleDescriptionAndPosts(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti', 'Свежие события компании');
        $this->fixtures->post('Первая статья', 'pervaya', '2026-09-01 10:00:00', [$id]);

        $body = $this->get('/category/novosti')->body;

        self::assertStringContainsString('Новости', $body);
        self::assertStringContainsString('Свежие события компании', $body);
        self::assertStringContainsString('Первая статья', $body);
        self::assertStringContainsString('1 статья', $body);
    }

    public function testUnknownCategoryGives404(): void
    {
        self::assertSame(404, $this->get('/category/net-takoy')->status);
    }

    public function testMalformedSlugGives404NotServerError(): void
    {
        self::assertSame(404, $this->get('/category/ЭТО НЕ СЛАГ')->status);
    }

    public function testEmptyCategoryOpensWithMessage(): void
    {
        $this->fixtures->category('Пустая', 'pustaya');

        $response = $this->get('/category/pustaya');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('пока нет опубликованных статей', $response->body);
    }

    public function testPaginationSplitsPostsAndLinksNextPage(): void
    {
        $this->seedPosts(25);

        $first = $this->get('/category/novosti')->body;

        self::assertSame(12, substr_count($first, 'class="post-card"'));
        self::assertStringContainsString('href="/category/novosti?page=2"', $first);
        self::assertStringContainsString('Вперёд', $first);

        $third = $this->get('/category/novosti', ['page' => '3'])->body;

        self::assertSame(1, substr_count($third, 'class="post-card"'));
    }

    public function testPageBeyondLastGives404(): void
    {
        $this->seedPosts(25);

        self::assertSame(404, $this->get('/category/novosti', ['page' => '4'])->status);
    }

    public function testGarbagePageFallsBackToFirst(): void
    {
        $this->seedPosts(25);

        $response = $this->get('/category/novosti', ['page' => 'вторая']);

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Статья 25', $response->body);
    }

    public function testSortingByViewsChangesOrder(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Непопулярная', 'a', '2026-09-10 10:00:00', [$id], 5);
        $this->fixtures->post('Популярная', 'b', '2026-09-01 10:00:00', [$id], 500);

        $byDate = $this->get('/category/novosti')->body;
        $byViews = $this->get('/category/novosti', ['sort' => 'views'])->body;

        self::assertLessThan(strpos($byDate, 'Популярная'), strpos($byDate, 'Непопулярная'));
        self::assertLessThan(strpos($byViews, 'Непопулярная'), strpos($byViews, 'Популярная'));
    }

    public function testSortLinkKeepsWorkingAcrossPages(): void
    {
        $this->seedPosts(25);

        $body = $this->get('/category/novosti', ['sort' => 'views', 'page' => '2'])->body;

        self::assertStringContainsString('href="/category/novosti?sort=views&amp;page=3"', $body);
    }

    public function testDefaultsAreAbsentFromUrls(): void
    {
        $this->seedPosts(25);

        $body = $this->get('/category/novosti', ['page' => '2'])->body;

        self::assertStringContainsString('href="/category/novosti"', $body, 'Первая страница без параметров');
        self::assertStringNotContainsString('sort=date', $body, 'Сортировка по умолчанию в адрес не пишется');
        self::assertStringNotContainsString('page=1"', $body);
    }

    public function testSwitchingSortReturnsToFirstPage(): void
    {
        $this->seedPosts(25);

        $body = $this->get('/category/novosti', ['page' => '3'])->body;

        self::assertStringContainsString('href="/category/novosti?sort=views"', $body);
        self::assertStringNotContainsString('sort=views&amp;page=3', $body);
    }

    public function testSinglePageHidesPagination(): void
    {
        $this->seedPosts(5);

        self::assertStringNotContainsString('class="pagination"', $this->get('/category/novosti')->body);
    }

    public function testDraftsAndFuturePostsAreHidden(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Опубликована', 'a', '2026-09-01 10:00:00', [$id]);
        $this->fixtures->post('Черновик', 'b', null, [$id]);
        $this->fixtures->post('Отложена', 'c', '2099-01-01 00:00:00', [$id]);

        $body = $this->get('/category/novosti')->body;

        self::assertStringContainsString('Опубликована', $body);
        self::assertStringNotContainsString('Черновик', $body);
        self::assertStringNotContainsString('Отложена', $body);
    }

    private function seedPosts(int $count): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');

        for ($n = 1; $n <= $count; ++$n) {
            $this->fixtures->post(
                sprintf('Статья %d', $n),
                sprintf('statya-%02d', $n),
                (new \DateTimeImmutable('2026-01-01 00:00:00'))->modify("+{$n} days")->format('Y-m-d H:i:s'),
                [$id],
            );
        }
    }
}
