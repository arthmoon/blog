<?php

declare(strict_types=1);

namespace App\Tests\Integration\Blog;

use App\Blog\Application\Command\SeedBlog;
use App\Blog\Application\Command\SeedBlogHandler;
use App\Blog\Infrastructure\Event\BlogListeners;
use App\Blog\Infrastructure\Event\ListenerDomainEventDispatcher;
use App\Blog\Infrastructure\Persistence\Mysql\PdoCategoryRepository;
use App\Blog\Infrastructure\Persistence\Mysql\PdoHomePageQuery;
use App\Blog\Infrastructure\Persistence\Mysql\PdoPostRepository;
use App\Blog\Infrastructure\Projection\CategoryLatestPostsProjector;
use App\Blog\Infrastructure\Seeder\BlogPurger;
use App\Shared\Infrastructure\Database\TransactionManager;
use App\Tests\Integration\IntegrationTestCase;
use App\Tests\Support\StaticSeedContent;
use App\Tests\Support\StubCoverImageFactory;

/**
 * Сквозная проверка стороны записи: домен, репозитории, событие, проекция.
 *
 * Обложки здесь заглушка — GD проверяется отдельным тестом, а тащить
 * файловую систему в тест про базу незачем.
 */
final class SeedBlogTest extends IntegrationTestCase
{
    private CategoryLatestPostsProjector $projector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projector = new CategoryLatestPostsProjector($this->connection);
    }

    public function testSeedFillsTablesAndProjection(): void
    {
        $result = $this->seed(4, 20);

        self::assertSame(4, $result->categories);
        self::assertSame(20, $result->posts);
        self::assertSame(4, $this->rowCount('categories'));
        self::assertSame(20, $this->rowCount('posts'));
        self::assertGreaterThan(0, $this->rowCount('category_latest_posts'));
    }

    public function testEveryPostIsPublished(): void
    {
        $this->seed(3, 12);

        self::assertSame(
            0,
            (int) $this->connection->query('SELECT COUNT(*) FROM posts WHERE published_at IS NULL')->fetchColumn(),
        );
    }

    public function testProjectionMatchesFullRebuild(): void
    {
        $this->seed(4, 20);

        $viaEvents = $this->dumpProjection();

        $this->projector->rebuildAll();
        $viaRebuild = $this->dumpProjection();

        self::assertSame(
            $viaRebuild,
            $viaEvents,
            'Проекция, собранная по событиям, обязана совпадать с пересчётом с нуля',
        );
    }

    public function testFeedHoldsAtMostThreePostsPerCategory(): void
    {
        $this->seed(2, 30);

        $sizes = $this->connection
            ->query('SELECT COUNT(*) AS n FROM category_latest_posts GROUP BY category_id')
            ->fetchAll();

        foreach ($sizes as $row) {
            self::assertLessThanOrEqual(CategoryLatestPostsProjector::FEED_SIZE, (int) $row['n']);
        }
    }

    public function testHomePageReadsSeededData(): void
    {
        $this->seed(3, 15);

        $sections = (new PdoHomePageQuery($this->connection))->categoriesWithLatestPosts(3);

        self::assertNotSame([], $sections);

        foreach ($sections as $section) {
            self::assertNotSame([], $section->posts);
            self::assertLessThanOrEqual(3, \count($section->posts));
        }
    }

    public function testPurgeEmptiesEverything(): void
    {
        $this->seed(3, 10);

        (new BlogPurger($this->connection))->purge();

        foreach (['categories', 'posts', 'post_category', 'category_latest_posts'] as $table) {
            self::assertSame(0, $this->rowCount($table), sprintf('Таблица %s должна быть пуста', $table));
        }
    }

    public function testSeedIsRepeatableAfterPurge(): void
    {
        $this->seed(3, 10);
        (new BlogPurger($this->connection))->purge();
        $second = $this->seed(3, 10);

        self::assertSame(10, $second->posts, 'Повторный запуск не должен падать на дубле слага');
    }

    private function seed(int $categories, int $posts): \App\Blog\Application\Command\SeedBlogResult
    {
        $dispatcher = new ListenerDomainEventDispatcher();
        BlogListeners::register($dispatcher, $this->projector);

        $handler = new SeedBlogHandler(
            new PdoCategoryRepository($this->connection),
            new PdoPostRepository($this->connection, new TransactionManager($this->connection), $dispatcher),
            new StaticSeedContent(),
            new StubCoverImageFactory(),
        );

        return $handler(new SeedBlog($categories, $posts, new \DateTimeImmutable('2026-09-20 12:00:00')));
    }

    private function rowCount(string $table): int
    {
        return (int) $this->connection->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    /**
     * @return list<array{int, int, int}>
     */
    private function dumpProjection(): array
    {
        $rows = $this->connection
            ->query('SELECT category_id, position, post_id FROM category_latest_posts ORDER BY category_id, position')
            ->fetchAll();

        return array_map(
            static fn (array $row): array => [(int) $row['category_id'], (int) $row['position'], (int) $row['post_id']],
            $rows,
        );
    }
}
