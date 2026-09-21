<?php

declare(strict_types=1);

namespace App\Blog\Application\ReadModel;

/**
 * Секция главной страницы: категория и её последние статьи.
 */
final readonly class CategoryWithPosts
{
    /**
     * @param list<PostListItem> $posts
     */
    public function __construct(
        public CategoryRef $category,
        public array $posts,
    ) {
    }
}
