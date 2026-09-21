<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Blog\Domain\Entity\Category;
use App\Blog\Domain\Repository\CategoryRepositoryInterface;
use App\Blog\Domain\ValueObject\CategoryId;

/**
 * Хранилище категорий в памяти. Имитирует главное, ради чего нужен
 * настоящий репозиторий на этом шаге: выдачу идентификатора базой.
 */
final class InMemoryCategoryRepository implements CategoryRepositoryInterface
{
    /** @var list<Category> */
    public array $saved = [];

    private int $nextId = 1;

    public function save(Category $category): void
    {
        if ($category->isNew()) {
            $category->assignId(CategoryId::fromInt($this->nextId++));
            $this->saved[] = $category;
        }
    }
}
