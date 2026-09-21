<?php

declare(strict_types=1);

namespace App\Blog\Domain\Event;

use App\Blog\Domain\ValueObject\CategoryId;
use App\Blog\Domain\ValueObject\PostId;

/**
 * Статья опубликована: ленты перечисленных категорий больше не актуальны.
 */
final readonly class PostPublished implements DomainEvent
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
