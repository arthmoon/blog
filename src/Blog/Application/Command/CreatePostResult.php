<?php

declare(strict_types=1);

namespace App\Blog\Application\Command;

final readonly class CreatePostResult
{
    /**
     * @param list<string> $categorySlugs
     */
    public function __construct(
        public int $postId,
        public string $slug,
        public bool $published,
        public array $categorySlugs,
    ) {
    }
}
