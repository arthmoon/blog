<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Domain\ValueObject;

use App\Blog\Domain\ValueObject\Slug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    public function testAcceptsValidValue(): void
    {
        self::assertSame('php-8-3-release', Slug::fromString('php-8-3-release')->value);
    }

    public function testIsStringable(): void
    {
        self::assertSame('news', (string) Slug::fromString('news'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'пустая строка' => [''];
        yield 'только пробелы' => ['   '];
        yield 'верхний регистр' => ['News'];
        yield 'пробел внутри' => ['hello world'];
        yield 'кириллица' => ['новости'];
        yield 'ведущий дефис' => ['-news'];
        yield 'замыкающий дефис' => ['news-'];
        yield 'двойной дефис' => ['news--today'];
        yield 'слеш' => ['news/today'];
        yield 'слишком длинный' => [str_repeat('a', 201)];
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValue(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Slug::fromString($value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function titles(): iterable
    {
        yield 'латиница' => ['Hello World', 'hello-world'];
        yield 'кириллица' => ['Привет, мир!', 'privet-mir'];
        yield 'ё и щ' => ['Ёжик в щели', 'ezhik-v-scheli'];
        yield 'мягкий знак выпадает' => ['Соль', 'sol'];
        yield 'цифры сохраняются' => ['PHP 8.3 вышел', 'php-8-3-vyshel'];
        yield 'лишние разделители схлопываются' => ['  Много   —  пробелов  ', 'mnogo-probelov'];
    }

    #[DataProvider('titles')]
    public function testBuildsSlugFromTitle(string $title, string $expected): void
    {
        self::assertSame($expected, Slug::fromTitle($title)->value);
    }

    public function testTruncatesLongTitleAtWordBoundary(): void
    {
        $slug = Slug::fromTitle(str_repeat('слово ', 60));

        self::assertLessThanOrEqual(200, mb_strlen($slug->value));
        self::assertStringEndsWith('slovo', $slug->value);
    }

    public function testRejectsTitleWithoutUsableCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Slug::fromTitle('!!! ??? ...');
    }

    public function testEquality(): void
    {
        self::assertTrue(Slug::fromString('news')->equals(Slug::fromString('news')));
        self::assertFalse(Slug::fromString('news')->equals(Slug::fromString('blog')));
    }
}
