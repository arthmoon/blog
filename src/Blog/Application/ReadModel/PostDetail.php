<?php

declare(strict_types=1);

namespace App\Blog\Application\ReadModel;

/**
 * Страница статьи целиком.
 *
 * Идентификатор нужен двум потребителям: отложенной команде подсчёта
 * просмотра и запросу похожих статей.
 */
final readonly class PostDetail
{
    /**
     * @param list<CategoryRef> $categories
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $description,
        public string $body,
        public ?string $imageUrl,
        public int $views,
        public \DateTimeImmutable $publishedAt,
        public array $categories,
    ) {
    }
}
