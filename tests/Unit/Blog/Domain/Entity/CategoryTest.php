<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Domain\Entity;

use App\Blog\Domain\Entity\Category;
use App\Blog\Domain\ValueObject\CategoryId;
use App\Blog\Domain\ValueObject\Slug;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function testNewCategoryHasNoIdentityYet(): void
    {
        $category = Category::create('Новости');

        self::assertTrue($category->isNew());
        self::assertNull($category->id());
    }

    public function testSlugIsBuiltFromTitle(): void
    {
        $category = Category::create('Новости компании');

        self::assertSame('novosti-kompanii', $category->slug()->value);
    }

    public function testExplicitSlugWins(): void
    {
        $category = Category::create('Новости компании', '', Slug::fromString('company-news'));

        self::assertSame('company-news', $category->slug()->value);
    }

    public function testTrimsTitleAndDescription(): void
    {
        $category = Category::create('  Новости  ', "  Свежие события  \n");

        self::assertSame('Новости', $category->title());
        self::assertSame('Свежие события', $category->description());
    }

    public function testDescriptionIsOptional(): void
    {
        self::assertSame('', Category::create('Новости')->description());
    }

    public function testRejectsEmptyTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Category::create('   ');
    }

    public function testRejectsTooLongTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Category::create(str_repeat('а', 151));
    }

    public function testRejectsTooLongDescription(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Category::create('Новости', str_repeat('а', 1001));
    }

    public function testRepositoryAssignsIdentityAfterInsert(): void
    {
        $category = Category::create('Новости');
        $category->assignId(CategoryId::fromInt(5));

        self::assertFalse($category->isNew());
        self::assertSame(5, $category->id()?->value);
    }

    public function testIdentityCannotBeReassigned(): void
    {
        $category = Category::create('Новости');
        $category->assignId(CategoryId::fromInt(5));

        $this->expectException(\LogicException::class);

        $category->assignId(CategoryId::fromInt(6));
    }

    public function testRestoreKeepsStoredState(): void
    {
        $category = Category::restore(
            CategoryId::fromInt(3),
            'Новости',
            Slug::fromString('news'),
            'Свежие события',
        );

        self::assertFalse($category->isNew());
        self::assertSame(3, $category->id()?->value);
        self::assertSame('Новости', $category->title());
        self::assertSame('news', $category->slug()->value);
        self::assertSame('Свежие события', $category->description());
    }
}
