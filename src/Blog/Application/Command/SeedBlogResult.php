<?php

declare(strict_types=1);

namespace App\Blog\Application\Command;

/**
 * Что получилось создать — консольная команда печатает это пользователю.
 */
final readonly class SeedBlogResult
{
    public function __construct(
        public int $categories,
        public int $posts,
    ) {
    }
}
