<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Domain\Entity;

use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Event\PostCategoriesChanged;
use App\Blog\Domain\Event\PostPublished;
use App\Blog\Domain\Event\PostUnpublished;
use App\Blog\Domain\ValueObject\CategoryId;
use App\Blog\Domain\ValueObject\PostId;
use PHPUnit\Framework\TestCase;

final class PostEventsTest extends TestCase
{
    public function testDraftRecordsNothing(): void
    {
        self::assertSame([], $this->draft()->releaseEvents());
    }

    public function testPublishRecordsEventWithAffectedCategories(): void
    {
        $post = $this->persisted([1, 2]);
        $post->publish(new \DateTimeImmutable('2026-09-01 10:00:00'));

        $events = $post->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(PostPublished::class, $events[0]);
        self::assertSame(1, $events[0]->postId->value);
        self::assertSame([1, 2], $this->values($events[0]->affectedCategoryIds()));
    }

    public function testEventsAreReleasedOnlyOnce(): void
    {
        $post = $this->persisted();
        $post->publish(new \DateTimeImmutable('2026-09-01 10:00:00'));

        self::assertCount(1, $post->releaseEvents());
        self::assertSame([], $post->releaseEvents(), 'Повторный вызов не должен отдавать те же события');
    }

    public function testUnpublishRecordsEvent(): void
    {
        $post = $this->persisted();
        $post->publish(new \DateTimeImmutable('2026-09-01 10:00:00'));
        $post->releaseEvents();

        $post->unpublish();
        $events = $post->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(PostUnpublished::class, $events[0]);
    }

    public function testCategoryChangeAffectsBothOldAndNewCategories(): void
    {
        $post = $this->persisted([1, 2]);
        $post->changeCategories([CategoryId::fromInt(2), CategoryId::fromInt(3)]);

        $events = $post->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(PostCategoriesChanged::class, $events[0]);
        self::assertSame([1, 2], $this->values($events[0]->previousCategoryIds));
        self::assertSame([2, 3], $this->values($events[0]->currentCategoryIds));
        self::assertSame(
            [1, 2, 3],
            $this->values($events[0]->affectedCategoryIds()),
            'Перестроить нужно и покинутую категорию, и новую',
        );
    }

    public function testReorderingSameCategoriesRecordsNothing(): void
    {
        $post = $this->persisted([1, 2]);
        $post->changeCategories([CategoryId::fromInt(2), CategoryId::fromInt(1)]);

        self::assertSame([], $post->releaseEvents());
    }

    public function testDraftCategoryChangeRecordsNothing(): void
    {
        $post = $this->draft();
        $post->changeCategories([CategoryId::fromInt(9)]);

        self::assertSame([], $post->releaseEvents(), 'У черновика ещё нет проекции, инвалидировать нечего');
    }

    /**
     * @param list<int> $categoryIds
     */
    private function draft(array $categoryIds = [1]): Post
    {
        return Post::create(
            'Заголовок статьи',
            'Короткое описание',
            'Текст статьи',
            array_map(static fn (int $id): CategoryId => CategoryId::fromInt($id), $categoryIds),
        );
    }

    /**
     * @param list<int> $categoryIds
     */
    private function persisted(array $categoryIds = [1]): Post
    {
        $post = $this->draft($categoryIds);
        $post->assignId(PostId::fromInt(1));

        return $post;
    }

    /**
     * @param list<CategoryId> $ids
     *
     * @return list<int>
     */
    private function values(array $ids): array
    {
        $values = array_map(static fn (CategoryId $id): int => $id->value, $ids);
        sort($values);

        return $values;
    }
}
