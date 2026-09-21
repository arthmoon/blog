<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Persistence\Mysql;

use App\Blog\Domain\Entity\Category;
use App\Blog\Domain\Repository\CategoryRepositoryInterface;
use App\Blog\Domain\ValueObject\CategoryId;

final readonly class PdoCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(private \PDO $connection)
    {
    }

    public function save(Category $category): void
    {
        $category->isNew() ? $this->insert($category) : $this->update($category);
    }

    private function insert(Category $category): void
    {
        $sql = <<<'SQL'
            INSERT INTO categories (title, slug, description)
            VALUES (:title, :slug, :description)
            SQL;

        $this->connection->prepare($sql)->execute([
            'title' => $category->title(),
            'slug' => $category->slug()->value,
            'description' => $category->description(),
        ]);

        $category->assignId(CategoryId::fromInt((int) $this->connection->lastInsertId()));
    }

    private function update(Category $category): void
    {
        $sql = <<<'SQL'
            UPDATE categories
               SET title = :title, slug = :slug, description = :description
             WHERE id = :id
            SQL;

        $this->connection->prepare($sql)->execute([
            'title' => $category->title(),
            'slug' => $category->slug()->value,
            'description' => $category->description(),
            'id' => $category->id()?->value,
        ]);
    }
}
