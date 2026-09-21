<?php

declare(strict_types=1);

namespace App\Tests\Integration\Blog;

use App\Blog\Application\ReadModel\CategoryWithPosts;
use App\Blog\Application\ReadModel\PostListItem;
use App\Blog\Infrastructure\Persistence\Mysql\PdoHomePageQuery;
use App\Blog\Infrastructure\Projection\CategoryLatestPostsProjector;
use App\Tests\Integration\IntegrationTestCase;

final class HomePageQueryTest extends IntegrationTestCase
{
    private PdoHomePageQuery $query;

    private CategoryLatestPostsProjector $projector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->query = new PdoHomePageQuery($this->connection);
        $this->projector = new CategoryLatestPostsProjector($this->connection);
    }

    public function testShowsThreeNewestPostsPerCategory(): void
    {
        $news = $this->fixtures->category('Новости', 'novosti');

        foreach (range(1, 5) as $n) {
            $this->fixtures->post("Статья {$n}", "statya-{$n}", sprintf('2026-09-%02d 10:00:00', $n), [$news]);
        }

        $this->projector->rebuildAll();

        $sections = $this->query->categoriesWithLatestPosts(3);

        self::assertCount(1, $sections);
        self::assertSame(['Статья 5', 'Статья 4', 'Статья 3'], $this->titles($sections[0]));
    }

    public function testCategoryWithoutPublishedPostsIsAbsent(): void
    {
        $withPosts = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->category('Пустая', 'pustaya');
        $this->fixtures->post('Статья', 'statya', '2026-09-01 10:00:00', [$withPosts]);

        $this->projector->rebuildAll();

        $sections = $this->query->categoriesWithLatestPosts(3);

        self::assertCount(1, $sections);
        self::assertSame('Новости', $sections[0]->category->title);
    }

    public function testDraftsAndFuturePostsAreHidden(): void
    {
        $category = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Опубликована', 'published', '2026-09-01 10:00:00', [$category]);
        $this->fixtures->post('Черновик', 'draft', null, [$category]);
        $this->fixtures->post('Отложена', 'scheduled', '2099-01-01 00:00:00', [$category]);

        $this->projector->rebuildAll();

        $sections = $this->query->categoriesWithLatestPosts(3);

        self::assertSame(['Опубликована'], $this->titles($sections[0]));
    }

    public function testPostInTwoCategoriesAppearsInBoth(): void
    {
        $first = $this->fixtures->category('Аналитика', 'analitika');
        $second = $this->fixtures->category('Будни', 'budni');
        $this->fixtures->post('Общая', 'obschaya', '2026-09-01 10:00:00', [$first, $second]);

        $this->projector->rebuildAll();

        $sections = $this->query->categoriesWithLatestPosts(3);

        self::assertCount(2, $sections);
        self::assertSame(['Общая'], $this->titles($sections[0]));
        self::assertSame(['Общая'], $this->titles($sections[1]));
    }

    public function testCategoriesAreOrderedByTitle(): void
    {
        foreach (['Яблоки' => 'yabloki', 'Апельсины' => 'apelsiny', 'Бананы' => 'banany'] as $title => $slug) {
            $id = $this->fixtures->category($title, $slug);
            $this->fixtures->post("Статья {$slug}", "statya-{$slug}", '2026-09-01 10:00:00', [$id]);
        }

        $this->projector->rebuildAll();

        $titles = array_map(
            static fn (CategoryWithPosts $section): string => $section->category->title,
            $this->query->categoriesWithLatestPosts(3),
        );

        self::assertSame(['Апельсины', 'Бананы', 'Яблоки'], $titles);
    }

    public function testEqualDatesAreResolvedByIdDescending(): void
    {
        $category = $this->fixtures->category('Новости', 'novosti');

        foreach (range(1, 4) as $n) {
            $this->fixtures->post("Статья {$n}", "statya-{$n}", '2026-09-01 10:00:00', [$category]);
        }

        $this->projector->rebuildAll();

        self::assertSame(
            ['Статья 4', 'Статья 3', 'Статья 2'],
            $this->titles($this->query->categoriesWithLatestPosts(3)[0]),
            'Порядок при одинаковых датах должен быть устойчивым',
        );
    }

    public function testFewerPostsCanBeRequested(): void
    {
        $category = $this->fixtures->category('Новости', 'novosti');

        foreach (range(1, 3) as $n) {
            $this->fixtures->post("Статья {$n}", "statya-{$n}", sprintf('2026-09-0%d 10:00:00', $n), [$category]);
        }

        $this->projector->rebuildAll();

        self::assertSame(['Статья 3'], $this->titles($this->query->categoriesWithLatestPosts(1)[0]));
    }

    public function testMapsAllCardFields(): void
    {
        $category = $this->fixtures->category('Новости', 'novosti', 'Описание категории');
        $this->fixtures->post('Статья', 'statya', '2026-09-01 10:00:00', [$category], 42, 'posts/cover.jpg');

        $this->projector->rebuildAll();

        $post = $this->query->categoriesWithLatestPosts(3)[0]->posts[0];

        self::assertSame('Статья', $post->title);
        self::assertSame('statya', $post->slug);
        self::assertSame('Описание Статья', $post->description);
        self::assertSame('/uploads/posts/cover.jpg', $post->imageUrl);
        self::assertSame(42, $post->views);
        self::assertSame('2026-09-01 10:00', $post->publishedAt->format('Y-m-d H:i'));
    }

    public function testPostWithoutImageGivesNullUrl(): void
    {
        $category = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Статья', 'statya', '2026-09-01 10:00:00', [$category]);

        $this->projector->rebuildAll();

        self::assertNull($this->query->categoriesWithLatestPosts(3)[0]->posts[0]->imageUrl);
    }

    public function testEmptyBlogGivesEmptyResult(): void
    {
        $this->projector->rebuildAll();

        self::assertSame([], $this->query->categoriesWithLatestPosts(3));
    }

    public function testIncrementalRebuildMatchesFullRebuild(): void
    {
        $first = $this->fixtures->category('Аналитика', 'analitika');
        $second = $this->fixtures->category('Будни', 'budni');

        foreach (range(1, 6) as $n) {
            $this->fixtures->post(
                "Статья {$n}",
                "statya-{$n}",
                sprintf('2026-09-%02d 10:00:00', $n),
                0 === $n % 2 ? [$first] : [$first, $second],
            );
        }

        $this->projector->rebuildAll();
        $viaFullRebuild = $this->dump();

        $this->projector->rebuildFor([
            \App\Blog\Domain\ValueObject\CategoryId::fromInt($first),
            \App\Blog\Domain\ValueObject\CategoryId::fromInt($second),
        ]);
        $viaIncremental = $this->dump();

        self::assertSame($viaFullRebuild, $viaIncremental, 'Два пути наполнения проекции должны совпадать');
    }

    public function testRejectsNonPositiveLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->query->categoriesWithLatestPosts(0);
    }

    /**
     * @return list<string>
     */
    private function titles(CategoryWithPosts $section): array
    {
        return array_map(static fn (PostListItem $post): string => $post->title, $section->posts);
    }

    /**
     * @return list<array{int, int, int}>
     */
    private function dump(): array
    {
        $rows = $this->connection
            ->query('SELECT category_id, position, post_id FROM category_latest_posts ORDER BY category_id, position')
            ?->fetchAll() ?: [];

        return array_map(
            static fn (array $row): array => [(int) $row['category_id'], (int) $row['position'], (int) $row['post_id']],
            $rows,
        );
    }
}
