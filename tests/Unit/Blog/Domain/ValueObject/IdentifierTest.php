<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Domain\ValueObject;

use App\Blog\Domain\ValueObject\CategoryId;
use App\Blog\Domain\ValueObject\PostId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentifierTest extends TestCase
{
    public function testPostIdKeepsValue(): void
    {
        self::assertSame(42, PostId::fromInt(42)->value);
    }

    public function testCategoryIdKeepsValue(): void
    {
        self::assertSame(7, CategoryId::fromInt(7)->value);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidValues(): iterable
    {
        yield 'ноль' => [0];
        yield 'отрицательное' => [-1];
    }

    #[DataProvider('invalidValues')]
    public function testPostIdRejectsNonPositive(int $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PostId::fromInt($value);
    }

    #[DataProvider('invalidValues')]
    public function testCategoryIdRejectsNonPositive(int $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CategoryId::fromInt($value);
    }

    public function testEquality(): void
    {
        self::assertTrue(PostId::fromInt(1)->equals(PostId::fromInt(1)));
        self::assertFalse(PostId::fromInt(1)->equals(PostId::fromInt(2)));
    }
}
