<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Application\Command;

use App\Blog\Application\Command\SeedBlog;
use App\Blog\Application\Command\SeedBlogHandler;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Event\PostPublished;
use App\Tests\Support\InMemoryCategoryRepository;
use App\Tests\Support\InMemoryPostRepository;
use App\Tests\Support\StaticSeedContent;
use App\Tests\Support\StubCoverImageFactory;
use PHPUnit\Framework\TestCase;

final class SeedBlogHandlerTest extends TestCase
{
    private InMemoryCategoryRepository $categories;

    private InMemoryPostRepository $posts;

    private StubCoverImageFactory $covers;

    protected function setUp(): void
    {
        $this->categories = new InMemoryCategoryRepository();
        $this->posts = new InMemoryPostRepository();
        $this->covers = new StubCoverImageFactory();
    }

    public function testCreatesRequestedAmount(): void
    {
        $result = $this->seed(4, 10);

        self::assertSame(4, $result->categories);
        self::assertSame(10, $result->posts);
        self::assertCount(4, $this->categories->saved);
        self::assertCount(10, $this->posts->saved);
    }

    public function testEveryPostIsPublishedAndHasCover(): void
    {
        $this->seed(3, 6);

        foreach ($this->posts->saved as $post) {
            self::assertNotNull($post->publishedAt());
            self::assertNotNull($post->image());
        }

        self::assertSame(6, $this->covers->calls);
    }

    public function testPublicationDatesDescendFromNewest(): void
    {
        $latest = new \DateTimeImmutable('2026-09-20 12:00:00');
        $this->seed(2, 3, $latest);

        $dates = array_map(
            static fn (Post $post): string => $post->publishedAt()?->format('Y-m-d H:i') ?? '',
            $this->posts->saved,
        );

        self::assertSame(['2026-09-20 12:00', '2026-09-20 06:00', '2026-09-20 00:00'], $dates);
    }

    public function testEachPostIsSavedTwice(): void
    {
        $this->seed(2, 5);

        self::assertSame(
            10,
            $this->posts->saveCalls,
            'Сначала INSERT ради идентификатора, затем UPDATE с датой публикации',
        );
    }

    public function testPublicationEventReachesRepository(): void
    {
        $this->seed(2, 4);

        self::assertCount(4, $this->posts->dispatchedEvents);

        foreach ($this->posts->dispatchedEvents as $event) {
            self::assertInstanceOf(PostPublished::class, $event);
            self::assertNotSame([], $event->affectedCategoryIds());
        }
    }

    public function testSomePostsBelongToTwoCategories(): void
    {
        $this->seed(4, 9);

        $sizes = array_map(
            static fn (Post $post): int => \count($post->categoryIds()),
            $this->posts->saved,
        );

        self::assertSame([2, 1, 1, 2, 1, 1, 2, 1, 1], $sizes, 'Каждая третья статья — в двух категориях');
    }

    public function testViewCountsDiffer(): void
    {
        $this->seed(2, 5);

        $views = array_map(static fn (Post $post): int => $post->views()->value, $this->posts->saved);

        self::assertSame([0, 37, 74, 111, 148], $views);
        self::assertGreaterThan(1, \count(array_unique($views)), 'Иначе сортировку по просмотрам нечем показать');
    }

    public function testSingleCategoryDoesNotProduceDuplicates(): void
    {
        $this->seed(1, 3);

        foreach ($this->posts->saved as $post) {
            self::assertCount(1, $post->categoryIds());
        }
    }

    public function testRejectsNonPositiveCategoryCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SeedBlog(0, 10, new \DateTimeImmutable('2026-09-20 12:00:00'));
    }

    private function seed(int $categories, int $posts, ?\DateTimeImmutable $latest = null): \App\Blog\Application\Command\SeedBlogResult
    {
        $handler = new SeedBlogHandler(
            $this->categories,
            $this->posts,
            new StaticSeedContent(),
            $this->covers,
        );

        return $handler(new SeedBlog(
            $categories,
            $posts,
            $latest ?? new \DateTimeImmutable('2026-09-20 12:00:00'),
        ));
    }
}
