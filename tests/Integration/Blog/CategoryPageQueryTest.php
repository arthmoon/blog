<?php

declare(strict_types=1);

namespace App\Tests\Integration\Blog;

use App\Blog\Application\PostSort;
use App\Blog\Application\ReadModel\PostListItem;
use App\Blog\Application\ReadModel\PostsPage;
use App\Blog\Domain\ValueObject\CategoryId;
use App\Blog\Domain\ValueObject\Slug;
use App\Blog\Infrastructure\Persistence\Mysql\PdoCategoryPostsQuery;
use App\Blog\Infrastructure\Persistence\Mysql\PdoCategoryQuery;
use App\Tests\Integration\IntegrationTestCase;

final class CategoryPageQueryTest extends IntegrationTestCase
{
    private PdoCategoryQuery $categories;

    private PdoCategoryPostsQuery $posts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categories = new PdoCategoryQuery($this->connection);
        $this->posts = new PdoCategoryPostsQuery($this->connection);
    }

    public function testFindsCategoryBySlug(): void
    {
        $this->fixtures->category('Новости', 'novosti', 'Свежие события');

        $category = $this->categories->findBySlug(Slug::fromString('novosti'));

        self::assertNotNull($category);
        self::assertSame('Новости', $category->title);
        self::assertSame('novosti', $category->slug);
        self::assertSame('Свежие события', $category->description);
    }

    public function testUnknownSlugGivesNull(): void
    {
        self::assertNull($this->categories->findBySlug(Slug::fromString('net-takoy')));
    }

    public function testEmptyCategoryStillOpens(): void
    {
        $id = $this->fixtures->category('Пустая', 'pustaya');

        $page = $this->page($id, PostSort::Newest, 1);

        self::assertTrue($page->isEmpty());
        self::assertSame(0, $page->total);
        self::assertSame(1, $page->pageCount());
        self::assertFalse($page->isOutOfRange(), 'Пустая категория — не 404');
    }

    public function testPaginationSplitsPosts(): void
    {
        $id = $this->seedNumberedPosts(25);

        $first = $this->page($id, PostSort::Newest, 1);
        $second = $this->page($id, PostSort::Newest, 2);
        $third = $this->page($id, PostSort::Newest, 3);

        self::assertSame(25, $first->total);
        self::assertSame(3, $first->pageCount());
        self::assertCount(12, $first->items);
        self::assertCount(12, $second->items);
        self::assertCount(1, $third->items);
    }

    public function testPagesDoNotOverlap(): void
    {
        $id = $this->seedNumberedPosts(25);

        $seen = [];

        foreach ([1, 2, 3] as $number) {
            foreach ($this->titles($this->page($id, PostSort::Newest, $number)) as $title) {
                $seen[] = $title;
            }
        }

        self::assertCount(25, $seen);
        self::assertCount(25, array_unique($seen), 'Статья не должна попадать на две страницы');
    }

    public function testSortsByPublicationDate(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Старая', 'staraya', '2026-09-01 10:00:00', [$id]);
        $this->fixtures->post('Свежая', 'svezhaya', '2026-09-10 10:00:00', [$id]);
        $this->fixtures->post('Средняя', 'srednyaya', '2026-09-05 10:00:00', [$id]);

        self::assertSame(
            ['Свежая', 'Средняя', 'Старая'],
            $this->titles($this->page($id, PostSort::Newest, 1)),
        );
    }

    public function testSortsByViews(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Непопулярная', 'a', '2026-09-10 10:00:00', [$id], 5);
        $this->fixtures->post('Популярная', 'b', '2026-09-01 10:00:00', [$id], 500);
        $this->fixtures->post('Средняя', 'c', '2026-09-05 10:00:00', [$id], 50);

        self::assertSame(
            ['Популярная', 'Средняя', 'Непопулярная'],
            $this->titles($this->page($id, PostSort::Popular, 1)),
        );
    }

    public function testEqualViewsGiveStableOrder(): void
    {
        $id = $this->seedNumberedPosts(20, views: 7);

        $first = $this->titles($this->page($id, PostSort::Popular, 1));
        $second = $this->titles($this->page($id, PostSort::Popular, 2));

        self::assertCount(20, array_unique([...$first, ...$second]));
    }

    public function testDraftsAndFuturePostsAreExcludedFromCountAndItems(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Опубликована', 'a', '2026-09-01 10:00:00', [$id]);
        $this->fixtures->post('Черновик', 'b', null, [$id]);
        $this->fixtures->post('Отложена', 'c', '2099-01-01 00:00:00', [$id]);

        $page = $this->page($id, PostSort::Newest, 1);

        self::assertSame(1, $page->total);
        self::assertSame(['Опубликована'], $this->titles($page));
    }

    public function testPageBeyondLastIsReportedOutOfRange(): void
    {
        $id = $this->seedNumberedPosts(25);

        $page = $this->page($id, PostSort::Newest, 4);

        self::assertSame(25, $page->total, 'Количество нужно даже за пределами диапазона');
        self::assertTrue($page->isOutOfRange());
        self::assertTrue($page->isEmpty());
    }

    public function testPostInTwoCategoriesIsCountedInEach(): void
    {
        $first = $this->fixtures->category('Аналитика', 'analitika');
        $second = $this->fixtures->category('Будни', 'budni');
        $this->fixtures->post('Общая', 'obschaya', '2026-09-01 10:00:00', [$first, $second]);

        self::assertSame(1, $this->page($first, PostSort::Newest, 1)->total);
        self::assertSame(1, $this->page($second, PostSort::Newest, 1)->total);
    }

    public function testOtherCategoriesDoNotLeakIn(): void
    {
        $mine = $this->fixtures->category('Моя', 'moya');
        $other = $this->fixtures->category('Чужая', 'chuzhaya');
        $this->fixtures->post('Моя статья', 'a', '2026-09-01 10:00:00', [$mine]);
        $this->fixtures->post('Чужая статья', 'b', '2026-09-02 10:00:00', [$other]);

        self::assertSame(['Моя статья'], $this->titles($this->page($mine, PostSort::Newest, 1)));
    }

    public function testMapsCardFields(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Статья', 'statya', '2026-09-01 10:00:00', [$id], 42, 'posts/cover.jpg');

        $post = $this->page($id, PostSort::Newest, 1)->items[0];

        self::assertSame('Статья', $post->title);
        self::assertSame('statya', $post->slug);
        self::assertSame('Описание Статья', $post->description);
        self::assertSame('/uploads/posts/cover.jpg', $post->imageUrl);
        self::assertSame(42, $post->views);
        self::assertSame('2026-09-01 10:00', $post->publishedAt->format('Y-m-d H:i'));
    }

    private function page(int $categoryId, PostSort $sort, int $number, int $perPage = 12): PostsPage
    {
        return $this->posts->page(CategoryId::fromInt($categoryId), $sort, $number, $perPage);
    }

    private function seedNumberedPosts(int $count, int $views = 0): int
    {
        $id = $this->fixtures->category('Новости', 'novosti');

        for ($n = 1; $n <= $count; ++$n) {
            $this->fixtures->post(
                sprintf('Статья %02d', $n),
                sprintf('statya-%02d', $n),
                (new \DateTimeImmutable('2026-01-01 00:00:00'))->modify("+{$n} days")->format('Y-m-d H:i:s'),
                [$id],
                $views,
            );
        }

        return $id;
    }

    /**
     * @return list<string>
     */
    private function titles(PostsPage $page): array
    {
        return array_map(static fn (PostListItem $post): string => $post->title, $page->items);
    }
}
