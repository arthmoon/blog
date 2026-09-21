<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Persistence\Mysql;

use App\Blog\Application\Port\SimilarPostsQuery;
use App\Blog\Application\ReadModel\PostListItem;
use App\Blog\Domain\ValueObject\ImagePath;
use App\Blog\Domain\ValueObject\PostId;

/**
 * Похожие статьи.
 *
 * Критерий задание не задаёт, поэтому он наш: чем больше общих категорий,
 * тем выше релевантность; при равенстве выигрывает свежая. Дальше — id,
 * чтобы порядок не плавал между запросами.
 *
 * Если похожих меньше запрошенного, блок добирается свежими статьями блога.
 * Добирать «из тех же категорий» было бы бессмысленно: такие статьи уже все
 * попали в первый запрос. Пустой или куцый блок на странице выглядит
 * сломанным, поэтому расширяем выборку, а не оставляем дыру.
 */
final readonly class PdoSimilarPostsQuery implements SimilarPostsQuery
{
    private const string CARD_COLUMNS = 'p.title, p.slug, p.description, p.image, p.views, p.published_at';

    private const string PUBLISHED = 'p.published_at IS NOT NULL AND p.published_at <= NOW()';

    public function __construct(private \PDO $connection)
    {
    }

    public function forPost(PostId $postId, int $limit): array
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException(sprintf('Похожих статей должно быть хотя бы одна, запрошено %d.', $limit));
        }

        $similar = $this->bySharedCategories($postId, $limit);

        if (\count($similar) >= $limit) {
            // Ключи здесь — идентификаторы статей, наружу нужен список.
            return array_values($similar);
        }

        return [
            ...array_values($similar),
            ...$this->recent($postId, $limit - \count($similar), array_keys($similar)),
        ];
    }

    /**
     * Ключи массива — идентификаторы статей: по ним второй запрос исключает
     * уже отобранное.
     *
     * @return array<int, PostListItem>
     */
    private function bySharedCategories(PostId $postId, int $limit): array
    {
        $sql = sprintf(
            'SELECT p.id, %s, COUNT(*) AS shared
               FROM posts p
               JOIN post_category pc ON pc.post_id = p.id
              WHERE pc.category_id IN (
                        SELECT category_id FROM post_category WHERE post_id = :source_post
                    )
                AND p.id <> :excluded_post
                AND %s
              GROUP BY p.id
              ORDER BY shared DESC, p.published_at DESC, p.id DESC
              LIMIT :row_limit',
            self::CARD_COLUMNS,
            self::PUBLISHED,
        );

        $statement = $this->connection->prepare($sql);
        // Одно значение под двумя именами: при выключенной эмуляции
        // повторить имя параметра нельзя.
        $statement->bindValue('source_post', $postId->value, \PDO::PARAM_INT);
        $statement->bindValue('excluded_post', $postId->value, \PDO::PARAM_INT);
        $statement->bindValue('row_limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        $posts = [];

        foreach ($statement->fetchAll() as $row) {
            $posts[(int) $row['id']] = self::toListItem($row);
        }

        return $posts;
    }

    /**
     * @param list<int> $excludedIds
     *
     * @return list<PostListItem>
     */
    private function recent(PostId $postId, int $limit, array $excludedIds): array
    {
        $excluded = [$postId->value, ...$excludedIds];

        // Плейсхолдеры строятся по числу значений, а сами значения уходят
        // параметрами: список формируется кодом, но в SQL не подставляется.
        $placeholders = implode(', ', array_map(
            static fn (int $index): string => ':excluded_' . $index,
            array_keys($excluded),
        ));

        $sql = sprintf(
            'SELECT %s
               FROM posts p
              WHERE %s
                AND p.id NOT IN (%s)
              ORDER BY p.published_at DESC, p.id DESC
              LIMIT :row_limit',
            self::CARD_COLUMNS,
            self::PUBLISHED,
            $placeholders,
        );

        $statement = $this->connection->prepare($sql);

        foreach ($excluded as $index => $id) {
            $statement->bindValue('excluded_' . $index, $id, \PDO::PARAM_INT);
        }

        $statement->bindValue('row_limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return array_map(self::toListItem(...), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function toListItem(array $row): PostListItem
    {
        return new PostListItem(
            (string) $row['title'],
            (string) $row['slug'],
            (string) $row['description'],
            null === $row['image'] ? null : ImagePath::PUBLIC_PREFIX . (string) $row['image'],
            (int) $row['views'],
            new \DateTimeImmutable((string) $row['published_at'], new \DateTimeZone('UTC')),
        );
    }
}
