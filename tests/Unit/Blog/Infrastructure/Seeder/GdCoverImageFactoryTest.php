<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Infrastructure\Seeder;

use App\Blog\Infrastructure\Seeder\GdCoverImageFactory;
use PHPUnit\Framework\TestCase;

final class GdCoverImageFactoryTest extends TestCase
{
    private string $uploads;

    protected function setUp(): void
    {
        $this->uploads = sys_get_temp_dir() . '/blog-covers-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->uploads)) {
            return;
        }

        foreach (glob($this->uploads . '/posts/*.png') ?: [] as $file) {
            unlink($file);
        }

        @rmdir($this->uploads . '/posts');
        @rmdir($this->uploads);
    }

    public function testCreatesPngUnderPostsDirectory(): void
    {
        $path = (new GdCoverImageFactory($this->uploads))->create('Как мы ускорили сборку проекта');

        self::assertSame('posts/kak-my-uskorili-sborku-proekta.png', $path->value);
        self::assertFileExists($this->uploads . '/' . $path->value);
    }

    public function testImageHasExpectedSizeAndFormat(): void
    {
        $path = (new GdCoverImageFactory($this->uploads))->create('Разбираем миграции базы');

        $info = getimagesize($this->uploads . '/' . $path->value);

        self::assertNotFalse($info);
        self::assertSame(800, $info[0]);
        self::assertSame(450, $info[1]);
        self::assertSame('image/png', $info['mime']);
    }

    public function testDifferentTitlesGiveDifferentFiles(): void
    {
        $factory = new GdCoverImageFactory($this->uploads);

        $first = $factory->create('Первая статья');
        $second = $factory->create('Вторая статья');

        self::assertNotSame($first->value, $second->value);
        self::assertNotSame(
            md5_file($this->uploads . '/' . $first->value),
            md5_file($this->uploads . '/' . $second->value),
            'Обложки должны отличаться, иначе разницы между статьями не видно',
        );
    }

    public function testSameTitleGivesSameImage(): void
    {
        $factory = new GdCoverImageFactory($this->uploads);

        $path = $factory->create('Повторяемая статья');
        $firstHash = md5_file($this->uploads . '/' . $path->value);

        $factory->create('Повторяемая статья');

        self::assertSame($firstHash, md5_file($this->uploads . '/' . $path->value));
    }
}
