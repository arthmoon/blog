<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Быстрая подготовка данных прямым SQL.
 *
 * Намеренно в обход репозиториев и домена: тест читающего запроса не должен
 * падать из-за ошибки на стороне записи, иначе непонятно, что именно сломано.
 * Связку «репозиторий плюс проектор» проверяет отдельный тест.
 */
final readonly class BlogFixtures
{
    public function __construct(private \PDO $connection)
    {
    }

    public function category(string $title, string $slug, string $description = ''): int
    {
        $this->connection
            ->prepare('INSERT INTO categories (title, slug, description) VALUES (:title, :slug, :description)')
            ->execute(['title' => $title, 'slug' => $slug, 'description' => $description]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @param list<int> $categoryIds
     */
    public function post(
        string $title,
        string $slug,
        ?string $publishedAt,
        array $categoryIds,
        int $views = 0,
        ?string $image = null,
    ): int {
        $sql = <<<'SQL'
            INSERT INTO posts (title, slug, description, body, image, views, published_at)
            VALUES (:title, :slug, :description, :body, :image, :views, :published_at)
            SQL;

        $this->connection->prepare($sql)->execute([
            'title' => $title,
            'slug' => $slug,
            'description' => 'Описание ' . $title,
            'body' => 'Текст ' . $title,
            'image' => $image,
            'views' => $views,
            'published_at' => $publishedAt,
        ]);

        $postId = (int) $this->connection->lastInsertId();

        $link = $this->connection->prepare(
            'INSERT INTO post_category (post_id, category_id) VALUES (:post_id, :category_id)',
        );

        foreach ($categoryIds as $categoryId) {
            $link->execute(['post_id' => $postId, 'category_id' => $categoryId]);
        }

        return $postId;
    }
}
