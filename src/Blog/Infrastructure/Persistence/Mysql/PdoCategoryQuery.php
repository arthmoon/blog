<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Persistence\Mysql;

use App\Blog\Application\Port\CategoryQuery;
use App\Blog\Application\ReadModel\CategoryView;
use App\Blog\Domain\ValueObject\Slug;

final readonly class PdoCategoryQuery implements CategoryQuery
{
    public function __construct(private \PDO $connection)
    {
    }

    public function findBySlug(Slug $slug): ?CategoryView
    {
        $statement = $this->connection->prepare(
            'SELECT id, title, slug, description FROM categories WHERE slug = :slug',
        );
        $statement->execute(['slug' => $slug->value]);

        $row = $statement->fetch();

        if (false === $row) {
            return null;
        }

        return new CategoryView(
            (int) $row['id'],
            (string) $row['title'],
            (string) $row['slug'],
            (string) $row['description'],
        );
    }
}
