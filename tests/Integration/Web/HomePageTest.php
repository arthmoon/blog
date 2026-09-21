<?php

declare(strict_types=1);

namespace App\Tests\Integration\Web;

use App\Blog\Infrastructure\Projection\CategoryLatestPostsProjector;
use App\Tests\Integration\WebTestCase;

final class HomePageTest extends WebTestCase
{
    public function testShowsCategoriesWithTheirLatestPosts(): void
    {
        $news = $this->fixtures->category('Новости', 'novosti');
        $tech = $this->fixtures->category('Технологии', 'tehnologii');

        foreach (range(1, 4) as $n) {
            $this->fixtures->post("Новость {$n}", "novost-{$n}", sprintf('2026-09-%02d 10:00:00', $n), [$news]);
        }

        $this->fixtures->post('Про базы', 'pro-bazy', '2026-09-10 10:00:00', [$tech]);
        $this->rebuild();

        $body = $this->body();

        self::assertStringContainsString('Новости', $body);
        self::assertStringContainsString('Технологии', $body);
        self::assertStringContainsString('Новость 4', $body);
        self::assertStringContainsString('Про базы', $body);
    }

    public function testShowsOnlyThreeLatestPerCategory(): void
    {
        $news = $this->fixtures->category('Новости', 'novosti');

        foreach (range(1, 5) as $n) {
            $this->fixtures->post("Новость {$n}", "novost-{$n}", sprintf('2026-09-%02d 10:00:00', $n), [$news]);
        }

        $this->rebuild();
        $body = $this->body();

        self::assertStringContainsString('Новость 5', $body);
        self::assertStringContainsString('Новость 3', $body);
        self::assertStringNotContainsString('Новость 2', $body);
        self::assertStringNotContainsString('Новость 1', $body);
    }

    public function testEveryCategoryHasAllPostsButton(): void
    {
        $this->fixtures->post(
            'Статья',
            'statya',
            '2026-09-01 10:00:00',
            [$this->fixtures->category('Новости', 'novosti')],
        );
        $this->rebuild();

        $body = $this->body();

        self::assertStringContainsString('Все статьи', $body);
        self::assertStringContainsString('href="/category/novosti"', $body);
    }

    public function testLinksToPostPages(): void
    {
        $this->fixtures->post(
            'Статья',
            'kak-my-uskorili-sborku',
            '2026-09-01 10:00:00',
            [$this->fixtures->category('Новости', 'novosti')],
        );
        $this->rebuild();

        self::assertStringContainsString('href="/posts/kak-my-uskorili-sborku"', $this->body());
    }

    public function testEmptyBlogShowsMessageInsteadOfBlankPage(): void
    {
        $this->rebuild();

        self::assertSame(200, $this->get('/')->status);
        self::assertStringContainsString('Пока нет ни одной опубликованной статьи', $this->body());
    }

    public function testCategoryWithoutPublishedPostsIsHidden(): void
    {
        $this->fixtures->category('Пустая', 'pustaya');
        $this->fixtures->post(
            'Статья',
            'statya',
            '2026-09-01 10:00:00',
            [$this->fixtures->category('Новости', 'novosti')],
        );
        $this->rebuild();

        self::assertStringNotContainsString('Пустая', $this->body());
    }

    /**
     * Проверка сквозная: экранирование включается в рендерере, а убедиться
     * в нём нужно на настоящем шаблоне с настоящими данными из базы.
     */
    public function testDataFromDatabaseIsEscaped(): void
    {
        $this->fixtures->post(
            '<script>alert(1)</script>',
            'opasnaya',
            '2026-09-01 10:00:00',
            [$this->fixtures->category('<b>Категория</b>', 'kategoriya')],
        );
        $this->rebuild();

        $body = $this->body();

        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringNotContainsString('<b>Категория</b>', $body);
        self::assertStringContainsString('&lt;script&gt;', $body);
    }

    public function testRendersDateAndViewsInRussian(): void
    {
        $this->fixtures->post(
            'Статья',
            'statya',
            '2026-09-15 10:00:00',
            [$this->fixtures->category('Новости', 'novosti')],
            21,
        );
        $this->rebuild();

        $body = $this->body();

        self::assertStringContainsString('15 сентября 2026', $body);
        self::assertStringContainsString('21 просмотр', $body);
    }

    public function testResponseIsHtml(): void
    {
        $this->rebuild();
        $response = $this->get('/');

        self::assertSame(200, $response->status);
        self::assertSame('text/html; charset=utf-8', $response->headers['Content-Type']);
        self::assertStringContainsString('<html lang="ru">', $response->body);
    }

    private function rebuild(): void
    {
        (new CategoryLatestPostsProjector($this->connection))->rebuildAll();
    }

    private function body(): string
    {
        return $this->get('/')->body;
    }
}
