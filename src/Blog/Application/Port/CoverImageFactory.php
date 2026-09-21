<?php

declare(strict_types=1);

namespace App\Blog\Application\Port;

use App\Blog\Domain\ValueObject\ImagePath;

/**
 * Создаёт обложку статьи и возвращает путь к ней.
 *
 * Реализация рисует картинку через GD и кладёт файл в public/uploads.
 * Обработчику сидинга об этом знать незачем — ему нужен только путь.
 */
interface CoverImageFactory
{
    public function create(string $title): ImagePath;
}
