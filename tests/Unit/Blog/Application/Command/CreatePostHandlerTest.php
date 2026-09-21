<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Application\Command;

use App\Blog\Application\Command\CreatePost;
use App\Blog\Application\Command\CreatePostHandler;
use App\Blog\Application\Port\CategoryQuery;
use App\Blog\Application\ReadModel\CategoryView;
use App\Blog\Domain\Event\PostPublished;
use App\Blog\Domain\ValueObject\Slug;
use App\Tests\Support\InMemoryPostRepository;
use App\Tests\Support\StubCoverImageFactory;
use PHPUnit\Framework\TestCase;

final class CreatePostHandlerTest extends TestCase
{
    private InMemoryPostRepository $posts;

    private StubCoverImageFactory $covers;

    protected function setUp(): void
    {
        $this->posts = new InMemoryPostRepository();
        $this->covers = new StubCoverImageFactory();
    }

    public function testPublishesPostAndRecordsEvent(): void
    {
        $result = $this->handle($this->command());

        self::assertSame(1, $result->postId);
        self::assertSame('kak-my-uskorili-sborku', $result->slug);
        self::assertTrue($result->published);

        self::assertSame(2, $this->posts->saveCalls, 'INSERT ради идентификатора, затем UPDATE с датой');
        self::assertCount(1, $this->posts->dispatchedEvents);
        self::assertInstanceOf(PostPublished::class, $this->posts->dispatchedEvents[0]);
    }

    public function testDraftIsSavedOnceAndFiresNothing(): void
    {
        $result = $this->handle($this->command(publishedAt: null));

        self::assertFalse($result->published);
        self::assertSame(1, $this->posts->saveCalls);
        self::assertSame([], $this->posts->dispatchedEvents, 'Черновик ленты не трогает');
    }

    public function testEventCarriesAllRequestedCategories(): void
    {
        $this->handle($this->command(slugs: ['novosti', 'tehnologii']));

        $event = $this->posts->dispatchedEvents[0];

        self::assertInstanceOf(PostPublished::class, $event);
        self::assertCount(2, $event->affectedCategoryIds());
    }

    public function testCoverIsCreatedForTheTitle(): void
    {
        $this->handle($this->command());

        self::assertSame(1, $this->covers->calls);
        self::assertNotNull($this->posts->saved[0]->image());
    }

    public function testUnknownCategoryIsReported(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/net-takoy/');

        $this->handle($this->command(slugs: ['net-takoy']), known: ['novosti']);
    }

    public function testMalformedCategorySlugIsReported(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/не похоже на слаг/');

        $this->handle($this->command(slugs: ['НЕ СЛАГ']));
    }

    public function testEmptyCategoryListIsRejectedByTheCommand(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CreatePost('Заголовок', 'Описание', 'Текст', [], null);
    }

    /**
     * @param list<string> $slugs
     */
    private function command(array $slugs = ['novosti'], ?\DateTimeImmutable $publishedAt = new \DateTimeImmutable('2026-09-21 10:00:00')): CreatePost
    {
        return new CreatePost(
            'Как мы ускорили сборку',
            'Короткое описание',
            "Первый абзац.\n\nВторой абзац.",
            $slugs,
            $publishedAt,
        );
    }

    /**
     * @param list<string> $known
     */
    private function handle(CreatePost $command, array $known = ['novosti', 'tehnologii']): \App\Blog\Application\Command\CreatePostResult
    {
        $categories = new class ($known) implements CategoryQuery {
            /**
             * @param list<string> $known
             */
            public function __construct(private readonly array $known)
            {
            }

            public function findBySlug(Slug $slug): ?CategoryView
            {
                $index = array_search($slug->value, $this->known, true);

                return false === $index
                    ? null
                    : new CategoryView($index + 1, ucfirst($slug->value), $slug->value, '');
            }
        };

        return (new CreatePostHandler($categories, $this->posts, $this->covers))($command);
    }
}
