<?php

declare(strict_types=1);

namespace App\Blog\Application\ReadModel;

/**
 * Карточка статьи в списке.
 *
 * Текста статьи здесь нет намеренно: на страницу категории приходит до
 * двенадцати карточек, и тянуть двенадцать полных текстов ради анонсов —
 * самый дешёвый способ испортить время ответа на ровном месте.
 */
final readonly class PostListItem
{
    public function __construct(
        public string $title,
        public string $slug,
        public string $description,
        public ?string $imageUrl,
        public int $views,
        public \DateTimeImmutable $publishedAt,
    ) {
    }
}
