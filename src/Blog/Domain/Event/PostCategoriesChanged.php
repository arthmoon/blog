<?php

declare(strict_types=1);

namespace App\Blog\Domain\Event;

use App\Blog\Domain\ValueObject\CategoryId;
use App\Blog\Domain\ValueObject\PostId;

/**
 * У статьи сменился набор категорий.
 *
 * Событие несёт и старый набор, и новый: перестроить нужно ленты обеих
 * сторон, иначе статья останется висеть в категории, которую покинула.
 * Ровно этот случай не покрывается наивным «обновляем при добавлении».
 */
final readonly class PostCategoriesChanged implements DomainEvent
{
    /**
     * @param list<CategoryId> $previousCategoryIds
     * @param list<CategoryId> $currentCategoryIds
     */
    public function __construct(
        public PostId $postId,
        public array $previousCategoryIds,
        public array $currentCategoryIds,
    ) {
    }

    /**
     * @return list<CategoryId>
     */
    public function affectedCategoryIds(): array
    {
        $affected = [];

        foreach ([...$this->previousCategoryIds, ...$this->currentCategoryIds] as $categoryId) {
            $affected[$categoryId->value] = $categoryId;
        }

        return array_values($affected);
    }
}
