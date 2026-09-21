<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Persistence\Mysql;

use App\Blog\Application\Port\PostQuery;
use App\Blog\Application\ReadModel\CategoryRef;
use App\Blog\Application\ReadModel\PostDetail;
use App\Blog\Domain\ValueObject\ImagePath;
use App\Blog\Domain\ValueObject\Slug;

/**
 * Страница статьи.
 *
 * Два запроса вместо одного с JOIN: соединение с категориями размножило бы
 * строку статьи вместе с её текстом на каждую категорию, и текст пришлось бы
 * тащить по сети трижды ради трёх подписей. Второй запрос дешевле.
 */
final readonly class PdoPostQuery implements PostQuery
{
    public function __construct(private \PDO $connection)
    {
    }

    public function findBySlug(Slug $slug): ?PostDetail
    {
        $sql = <<<'SQL'
            SELECT id, title, slug, description, body, image, views, published_at
              FROM posts
             WHERE slug = :slug
               AND published_at IS NOT NULL
               AND published_at <= NOW()
            SQL;

        $statement = $this->connection->prepare($sql);
        $statement->execute(['slug' => $slug->value]);

        $row = $statement->fetch();

        if (false === $row) {
            return null;
        }

        $postId = (int) $row['id'];

        return new PostDetail(
            $postId,
            (string) $row['title'],
            (string) $row['slug'],
            (string) $row['description'],
            (string) $row['body'],
            null === $row['image'] ? null : ImagePath::PUBLIC_PREFIX . (string) $row['image'],
            (int) $row['views'],
            new \DateTimeImmutable((string) $row['published_at'], new \DateTimeZone('UTC')),
            $this->categoriesOf($postId),
        );
    }

    /**
     * @return list<CategoryRef>
     */
    private function categoriesOf(int $postId): array
    {
        $sql = <<<'SQL'
            SELECT c.title, c.slug
              FROM categories c
              JOIN post_category pc ON pc.category_id = c.id
             WHERE pc.post_id = :post_id
             ORDER BY c.title
            SQL;

        $statement = $this->connection->prepare($sql);
        $statement->execute(['post_id' => $postId]);

        return array_map(
            static fn (array $row): CategoryRef => new CategoryRef((string) $row['title'], (string) $row['slug']),
            $statement->fetchAll(),
        );
    }
}
