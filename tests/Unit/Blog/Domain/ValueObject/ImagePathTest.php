<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Domain\ValueObject;

use App\Blog\Domain\ValueObject\ImagePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImagePathTest extends TestCase
{
    public function testAcceptsNestedRelativePath(): void
    {
        self::assertSame('posts/2026/cover.jpg', ImagePath::fromString('posts/2026/cover.jpg')->value);
    }

    public function testBuildsPublicUrl(): void
    {
        self::assertSame('/uploads/posts/cover.webp', ImagePath::fromString('posts/cover.webp')->url());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPaths(): iterable
    {
        yield 'пустая строка' => [''];
        yield 'выход вверх' => ['../../etc/passwd.jpg'];
        yield 'выход вверх внутри' => ['posts/../../secret.jpg'];
        yield 'абсолютный путь' => ['/etc/passwd.jpg'];
        yield 'обратный слеш' => ['posts\\cover.jpg'];
        yield 'пробел' => ['posts/my cover.jpg'];
        yield 'без расширения' => ['posts/cover'];
        yield 'исполняемый файл' => ['posts/shell.php'];
        yield 'svg с возможным скриптом' => ['posts/logo.svg'];
        yield 'верхний регистр в пути' => ['Posts/Cover.jpg'];
        yield 'длиннее колонки в базе' => [str_repeat('a', 252) . '.jpg'];
    }

    #[DataProvider('invalidPaths')]
    public function testRejectsInvalidPath(string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ImagePath::fromString($path);
    }

    public function testEquality(): void
    {
        self::assertTrue(ImagePath::fromString('a/b.png')->equals(ImagePath::fromString('a/b.png')));
        self::assertFalse(ImagePath::fromString('a/b.png')->equals(ImagePath::fromString('a/c.png')));
    }
}
