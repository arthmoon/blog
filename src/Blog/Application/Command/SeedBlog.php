<?php

declare(strict_types=1);

namespace App\Blog\Application\Command;

/**
 * Наполнить блог тестовыми данными.
 *
 * Момент публикации самой свежей статьи передаётся снаружи, а не берётся
 * внутри как «сейчас»: так обработчик остаётся детерминированным и его
 * можно проверить тестом, не заводя порт часов.
 */
final readonly class SeedBlog
{
    public function __construct(
        public int $categoryCount,
        public int $postCount,
        public \DateTimeImmutable $latestPublishedAt,
    ) {
        if ($categoryCount < 1) {
            throw new \InvalidArgumentException(sprintf('Категорий должно быть хотя бы одна, запрошено %d.', $categoryCount));
        }

        if ($postCount < 0) {
            throw new \InvalidArgumentException(sprintf('Количество статей не может быть отрицательным, запрошено %d.', $postCount));
        }
    }
}
