<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Template;

use App\Shared\Infrastructure\Template\TemplateFunctions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TemplateFunctionsTest extends TestCase
{
    private TemplateFunctions $fmt;

    protected function setUp(): void
    {
        $this->fmt = new TemplateFunctions();
    }

    public function testFormatsDateInRussian(): void
    {
        self::assertSame('15 сентября 2026', $this->fmt->date(new \DateTimeImmutable('2026-09-15')));
        self::assertSame('1 января 2026', $this->fmt->date(new \DateTimeImmutable('2026-01-01')));
        self::assertSame('31 декабря 2025', $this->fmt->date(new \DateTimeImmutable('2025-12-31')));
    }

    public function testIsoDateForTimeTag(): void
    {
        self::assertSame('2026-09-15', $this->fmt->isoDate(new \DateTimeImmutable('2026-09-15 18:30:00')));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function viewCounts(): iterable
    {
        yield 'ноль' => [0, '0 просмотров'];
        yield 'один' => [1, '1 просмотр'];
        yield 'два' => [2, '2 просмотра'];
        yield 'четыре' => [4, '4 просмотра'];
        yield 'пять' => [5, '5 просмотров'];
        yield 'одиннадцать' => [11, '11 просмотров'];
        yield 'двенадцать' => [12, '12 просмотров'];
        yield 'четырнадцать' => [14, '14 просмотров'];
        yield 'двадцать один' => [21, '21 просмотр'];
        yield 'двадцать два' => [22, '22 просмотра'];
        yield 'сто' => [100, '100 просмотров'];
        yield 'сто один' => [101, '101 просмотр'];
        yield 'сто одиннадцать' => [111, '111 просмотров'];
        yield 'тысяча сто двадцать четыре' => [1124, '1124 просмотра'];
    }

    #[DataProvider('viewCounts')]
    public function testPluralisesViews(int $number, string $expected): void
    {
        self::assertSame($expected, $this->fmt->views($number));
    }

    public function testPluralisesPosts(): void
    {
        self::assertSame('1 статья', $this->fmt->posts(1));
        self::assertSame('3 статьи', $this->fmt->posts(3));
        self::assertSame('7 статей', $this->fmt->posts(7));
    }
}
