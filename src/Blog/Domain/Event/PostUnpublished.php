<?php

declare(strict_types=1);

namespace App\Blog\Domain\Event;

use App\Blog\Domain\ValueObject\CategoryId;
use App\Blog\Domain\ValueObject\PostId;

/**
 * Статья снята с публикации: в лентах её категорий образовалась дыра.
 */
final readonly class PostUnpublished implements DomainEvent
{
    /**
     * @param list<CategoryId> $categoryIds
     */
    public function __construct(
        public PostId $postId,
        public array $categoryIds,
    ) {
    }

    /**
     * @return list<CategoryId>
     */
    public function affectedCategoryIds(): array
    {
        return $this->categoryIds;
    }
}
