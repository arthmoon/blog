<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Blog\Application\Port\CoverImageFactory;
use App\Blog\Domain\ValueObject\ImagePath;

/**
 * Возвращает путь, не трогая файловую систему: тесту обработчика
 * незачем знать про GD.
 */
final class StubCoverImageFactory implements CoverImageFactory
{
    public int $calls = 0;

    public function create(string $title): ImagePath
    {
        ++$this->calls;

        return ImagePath::fromString(sprintf('posts/cover-%d.png', $this->calls));
    }
}
