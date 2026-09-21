<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Domain\ValueObject;

use App\Blog\Domain\ValueObject\ViewCount;
use PHPUnit\Framework\TestCase;

final class ViewCountTest extends TestCase
{
    public function testStartsAtZero(): void
    {
        self::assertSame(0, ViewCount::zero()->value);
    }

    public function testIncrementReturnsNewInstance(): void
    {
        $count = ViewCount::fromInt(41);
        $incremented = $count->increment();

        self::assertSame(42, $incremented->value);
        self::assertSame(41, $count->value, 'Исходное значение не должно меняться');
    }

    public function testRejectsNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ViewCount::fromInt(-1);
    }

    public function testEquality(): void
    {
        self::assertTrue(ViewCount::fromInt(7)->equals(ViewCount::fromInt(7)));
        self::assertFalse(ViewCount::fromInt(7)->equals(ViewCount::zero()));
    }
}
