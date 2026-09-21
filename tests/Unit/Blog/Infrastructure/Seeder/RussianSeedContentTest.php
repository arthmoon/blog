<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Infrastructure\Seeder;

use App\Blog\Application\Seed\CategoryDraft;
use App\Blog\Application\Seed\PostDraft;
use App\Blog\Domain\ValueObject\Slug;
use App\Blog\Infrastructure\Seeder\RussianSeedContent;
use PHPUnit\Framework\TestCase;

final class RussianSeedContentTest extends TestCase
{
    private RussianSeedContent $content;

    protected function setUp(): void
    {
        $this->content = new RussianSeedContent();
    }

    public function testReturnsRequestedAmount(): void
    {
        self::assertCount(5, $this->content->categories(5));
        self::assertCount(37, $this->content->posts(37));
    }

    public function testCategorySlugsAreUniqueEvenBeyondThePool(): void
    {
        $slugs = array_map(
            static fn (CategoryDraft $draft): string => Slug::fromTitle($draft->title)->value,
            $this->content->categories(25),
        );

        self::assertCount(25, array_unique($slugs), 'Слаг уникален в базе, дубль уронил бы сидер');
    }

    public function testPostSlugsAreUniqueOnLargeRuns(): void
    {
        $slugs = array_map(
            static fn (PostDraft $draft): string => Slug::fromTitle($draft->title)->value,
            $this->content->posts(200),
        );

        self::assertCount(200, array_unique($slugs));
    }

    public function testDescriptionsFitTheColumn(): void
    {
        foreach ($this->content->posts(50) as $draft) {
            self::assertNotSame('', $draft->description);
            self::assertLessThanOrEqual(500, mb_strlen($draft->description));
        }
    }

    public function testTitlesFitTheColumn(): void
    {
        foreach ($this->content->posts(200) as $draft) {
            self::assertLessThanOrEqual(200, mb_strlen($draft->title));
        }
    }

    public function testBodiesHaveSeveralParagraphsAndDiffer(): void
    {
        $bodies = array_map(
            static fn (PostDraft $draft): string => $draft->body,
            $this->content->posts(10),
        );

        foreach ($bodies as $body) {
            self::assertCount(4, explode("\n\n", $body));
        }

        self::assertGreaterThan(1, \count(array_unique($bodies)), 'Тексты не должны быть одинаковыми');
    }

    public function testGeneratorIsDeterministic(): void
    {
        $first = $this->content->posts(20);
        $second = (new RussianSeedContent())->posts(20);

        self::assertEquals($first, $second, 'Повторный запуск должен давать ту же базу');
    }

    public function testZeroCountGivesEmptyList(): void
    {
        self::assertSame([], $this->content->posts(0));
        self::assertSame([], $this->content->categories(0));
    }
}
