<?php

declare(strict_types=1);

namespace App\Blog\Application\Seed;

/**
 * Заготовка статьи от поставщика тестовых данных.
 */
final readonly class PostDraft
{
    public function __construct(
        public string $title,
        public string $description,
        public string $body,
    ) {
    }
}
