<?php

declare(strict_types=1);

namespace App\Blog\Application\Seed;

/**
 * Заготовка категории от поставщика тестовых данных.
 */
final readonly class CategoryDraft
{
    public function __construct(
        public string $title,
        public string $description,
    ) {
    }
}
