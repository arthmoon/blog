<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Projection;

use App\Blog\Domain\Event\PostCategoriesChanged;
use App\Blog\Domain\Event\PostPublished;
use App\Blog\Domain\Event\PostUnpublished;
use App\Blog\Domain\ValueObject\CategoryId;

/**
 * Поддерживает таблицу category_latest_posts в согласии с posts.
 *
 * Лента категории не правится точечно, а пересобирается целиком. Точечная
 * вставка требовала бы разобрать, куда именно попала статья и что при этом
 * выпало из тройки, — а это тот же самый запрос плюс арифметика позиций.
 * Пересборка одной категории стоит один индексный скан и всегда даёт
 * правильный результат, в том числе после снятия статьи с публикации.
 *
 * Проектор вызывается из диспетчера внутри транзакции репозитория,
 * поэтому своей транзакции не открывает.
 */
final readonly class CategoryLatestPostsProjector
{
    /**
     * Сколько статей показывает главная. Число живёт здесь, а не в схеме:
     * это решение представления, и менять его миграцией не должно.
     */
    public const int FEED_SIZE = 3;

    public function __construct(
        private \PDO $connection,
        private int $feedSize = self::FEED_SIZE,
    ) {
    }

    public function onPostPublished(PostPublished $event): void
    {
        $this->rebuildFor($event->affectedCategoryIds());
    }

    public function onPostUnpublished(PostUnpublished $event): void
    {
        $this->rebuildFor($event->affectedCategoryIds());
    }

    public function onPostCategoriesChanged(PostCategoriesChanged $event): void
    {
        $this->rebuildFor($event->affectedCategoryIds());
    }

    /**
     * @param list<CategoryId> $categoryIds
     */
    public function rebuildFor(array $categoryIds): void
    {
        foreach ($categoryIds as $categoryId) {
            $this->rebuildCategory($categoryId->value);
        }
    }

    /**
     * Полная пересборка одним запросом — для наполнения базы сидером
     * и как эталон, с которым сверяется инкрементальное обновление.
     */
    public function rebuildAll(): void
    {
        $this->connection->exec('DELETE FROM category_latest_posts');

        $sql = <<<'SQL'
            INSERT INTO category_latest_posts (category_id, position, post_id)
            SELECT category_id, position, post_id
              FROM (
                    SELECT pc.category_id                AS category_id,
                           p.id                          AS post_id,
                           ROW_NUMBER() OVER (
                               PARTITION BY pc.category_id
                               ORDER BY p.published_at DESC, p.id DESC
                           )                             AS position
                      FROM posts p
                      JOIN post_category pc ON pc.post_id = p.id
                     WHERE p.published_at IS NOT NULL
                       AND p.published_at <= NOW()
                   ) ranked
             WHERE position <= :feed_size
            SQL;

        $statement = $this->connection->prepare($sql);
        $statement->bindValue('feed_size', $this->feedSize, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Пересборка одной категории.
     *
     * Идентификатор категории подставляется под двумя разными именами.
     * Это не опечатка: при выключенной эмуляции подготовленных выражений
     * повторить одно имя дважды нельзя — PDO отдаёт серверу по одному
     * параметру на место подстановки.
     */
    private function rebuildCategory(int $categoryId): void
    {
        $this->connection
            ->prepare('DELETE FROM category_latest_posts WHERE category_id = :category_id')
            ->execute(['category_id' => $categoryId]);

        $sql = <<<'SQL'
            INSERT INTO category_latest_posts (category_id, position, post_id)
            SELECT :target_category, position, post_id
              FROM (
                    SELECT p.id AS post_id,
                           ROW_NUMBER() OVER (
                               ORDER BY p.published_at DESC, p.id DESC
                           )    AS position
                      FROM posts p
                      JOIN post_category pc ON pc.post_id = p.id
                     WHERE pc.category_id = :source_category
                       AND p.published_at IS NOT NULL
                       AND p.published_at <= NOW()
                   ) ranked
             WHERE position <= :feed_size
            SQL;

        $statement = $this->connection->prepare($sql);
        $statement->bindValue('target_category', $categoryId, \PDO::PARAM_INT);
        $statement->bindValue('source_category', $categoryId, \PDO::PARAM_INT);
        $statement->bindValue('feed_size', $this->feedSize, \PDO::PARAM_INT);
        $statement->execute();
    }
}
