<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Domain\Entity;

use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Exception\PostWithoutCategory;
use App\Blog\Domain\ValueObject\CategoryId;
use App\Blog\Domain\ValueObject\ImagePath;
use App\Blog\Domain\ValueObject\PostId;
use App\Blog\Domain\ValueObject\Slug;
use PHPUnit\Framework\TestCase;

final class PostTest extends TestCase
{
    public function testNewPostIsDraftWithoutViews(): void
    {
        $post = $this->draft();

        self::assertTrue($post->isNew());
        self::assertNull($post->publishedAt());
        self::assertSame(0, $post->views()->value);
        self::assertFalse($post->isPublished(new \DateTimeImmutable('now')));
    }

    public function testSlugIsBuiltFromTitle(): void
    {
        self::assertSame('kak-my-uskorili-sborku', $this->draft('Как мы ускорили сборку')->slug()->value);
    }

    public function testImageIsOptional(): void
    {
        self::assertNull($this->draft()->image());

        $post = Post::create('Заголовок', 'Описание', 'Текст', [CategoryId::fromInt(1)], ImagePath::fromString('posts/a.jpg'));

        self::assertSame('posts/a.jpg', $post->image()?->value);
    }

    public function testRejectsPostWithoutCategories(): void
    {
        $this->expectException(PostWithoutCategory::class);

        Post::create('Заголовок', 'Описание', 'Текст', []);
    }

    public function testDeduplicatesCategories(): void
    {
        $post = Post::create('Заголовок', 'Описание', 'Текст', [
            CategoryId::fromInt(1),
            CategoryId::fromInt(2),
            CategoryId::fromInt(1),
        ]);

        self::assertCount(2, $post->categoryIds());
    }

    public function testRejectsNonCategoryIdInList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        /** @phpstan-ignore-next-line намеренно неверный тип */
        Post::create('Заголовок', 'Описание', 'Текст', [1, 2]);
    }

    public function testRejectsEmptyTitleDescriptionOrBody(): void
    {
        $cases = [
            ['', 'Описание', 'Текст'],
            ['Заголовок', '', 'Текст'],
            ['Заголовок', 'Описание', '   '],
        ];

        foreach ($cases as [$title, $description, $body]) {
            try {
                Post::create($title, $description, $body, [CategoryId::fromInt(1)]);
                self::fail(sprintf('Ожидалось исключение для (%s, %s, %s)', $title, $description, $body));
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testPublishSetsMoment(): void
    {
        $post = $this->draft();
        $moment = new \DateTimeImmutable('2026-09-01 10:00:00');

        $post->publish($moment);

        self::assertEquals($moment, $post->publishedAt());
        self::assertTrue($post->isPublished(new \DateTimeImmutable('2026-09-02 10:00:00')));
    }

    public function testFuturePublicationIsNotVisibleYet(): void
    {
        $post = $this->draft();
        $post->publish(new \DateTimeImmutable('2026-12-31 00:00:00'));

        self::assertNotNull($post->publishedAt());
        self::assertFalse(
            $post->isPublished(new \DateTimeImmutable('2026-09-21 00:00:00')),
            'Заполненный published_at в будущем ещё не делает статью видимой',
        );
    }

    public function testCannotPublishTwice(): void
    {
        $post = $this->draft();
        $post->publish(new \DateTimeImmutable('2026-09-01 10:00:00'));

        $this->expectException(\LogicException::class);

        $post->publish(new \DateTimeImmutable('2026-09-02 10:00:00'));
    }

    public function testUnpublishReturnsPostToDraft(): void
    {
        $post = $this->draft();
        $post->publish(new \DateTimeImmutable('2026-09-01 10:00:00'));
        $post->unpublish();

        self::assertNull($post->publishedAt());
    }

    public function testCannotUnpublishDraft(): void
    {
        $this->expectException(\LogicException::class);

        $this->draft()->unpublish();
    }

    public function testChangeCategoriesKeepsInvariant(): void
    {
        $post = $this->draft();
        $post->changeCategories([CategoryId::fromInt(7)]);

        self::assertSame([7], array_map(static fn (CategoryId $id): int => $id->value, $post->categoryIds()));

        $this->expectException(PostWithoutCategory::class);

        $post->changeCategories([]);
    }

    public function testRegisterViewIncrementsSnapshot(): void
    {
        $post = $this->draft();
        $post->registerView();
        $post->registerView();

        self::assertSame(2, $post->views()->value);
    }

    public function testIdentityCannotBeReassigned(): void
    {
        $post = $this->draft();
        $post->assignId(PostId::fromInt(1));

        $this->expectException(\LogicException::class);

        $post->assignId(PostId::fromInt(2));
    }

    public function testExplicitSlugWins(): void
    {
        $post = Post::create(
            'Заголовок',
            'Описание',
            'Текст',
            [CategoryId::fromInt(1)],
            null,
            Slug::fromString('custom-slug'),
        );

        self::assertSame('custom-slug', $post->slug()->value);
    }

    private function draft(string $title = 'Заголовок статьи'): Post
    {
        return Post::create($title, 'Короткое описание', 'Текст статьи', [CategoryId::fromInt(1)]);
    }
}
