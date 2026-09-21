<?php

declare(strict_types=1);

namespace App\Blog\Application\ReadModel;

/**
 * Шапка страницы категории: название и описание.
 *
 * Идентификатор нужен, чтобы следующим запросом забрать страницу статей,
 * поэтому здесь он есть, в отличие от CategoryRef.
 */
final readonly class CategoryView
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public string $description,
    ) {
    }
}
