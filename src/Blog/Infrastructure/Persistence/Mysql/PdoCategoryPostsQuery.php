<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Persistence\Mysql;

use App\Blog\Application\Port\CategoryPostsQuery;
use App\Blog\Application\PostSort;
use App\Blog\Application\ReadModel\PostListItem;
use App\Blog\Application\ReadModel\PostsPage;
use App\Blog\Domain\ValueObject\CategoryId;
use App\Blog\Domain\ValueObject\ImagePath;

/**
 * Страница статей категории.
 *
 * Общее количество берётся отдельным COUNT(*). SQL_CALC_FOUND_ROWS объявлен
 * устаревшим в MySQL 8 и мешает оптимизатору: он запрещает раннее прерывание
 * по LIMIT. Два простых запроса отрабатывают быстрее одного хитрого.
 */
final readonly class PdoCategoryPostsQuery implements CategoryPostsQuery
{
    private const string PUBLISHED_CONDITION = 'pc.category_id = :category_id
                       AND p.published_at IS NOT NULL
                       AND p.published_at <= NOW()';

    public function __construct(private \PDO $connection)
    {
    }

    public function page(CategoryId $categoryId, PostSort $sort, int $page, int $perPage): PostsPage
    {
        $total = $this->countPublished($categoryId);

        // Пустая страница создаётся первой: конструктор заодно проверяет
        // корректность номера страницы и её размера.
        $empty = PostsPage::create([], $total, $page, $perPage);

        // За последней страницей читать нечего — второй запрос не нужен.
        // Контроллер увидит isOutOfRange() и ответит 404.
        if (0 === $total || $empty->isOutOfRange()) {
            return $empty;
        }

        return PostsPage::create(
            $this->fetchPage($categoryId, $sort, $empty->offset(), $perPage),
            $total,
            $page,
            $perPage,
        );
    }

    private function countPublished(CategoryId $categoryId): int
    {
        $sql = sprintf(
            'SELECT COUNT(*)
               FROM posts p
               JOIN post_category pc ON pc.post_id = p.id
              WHERE %s',
            self::PUBLISHED_CONDITION,
        );

        $statement = $this->connection->prepare($sql);
        $statement->execute(['category_id' => $categoryId->value]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<PostListItem>
     */
    private function fetchPage(CategoryId $categoryId, PostSort $sort, int $offset, int $perPage): array
    {
        $sql = sprintf(
            'SELECT p.title, p.slug, p.description, p.image, p.views, p.published_at
               FROM posts p
               JOIN post_category pc ON pc.post_id = p.id
              WHERE %s
              ORDER BY %s
              LIMIT :row_limit OFFSET :row_offset',
            self::PUBLISHED_CONDITION,
            self::orderBy($sort),
        );

        $statement = $this->connection->prepare($sql);
        $statement->bindValue('category_id', $categoryId->value, \PDO::PARAM_INT);
        // Целочисленный тип обязателен: со строкой MySQL отвергнет LIMIT.
        $statement->bindValue('row_limit', $perPage, \PDO::PARAM_INT);
        $statement->bindValue('row_offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): PostListItem => new PostListItem(
                (string) $row['title'],
                (string) $row['slug'],
                (string) $row['description'],
                null === $row['image'] ? null : ImagePath::PUBLIC_PREFIX . (string) $row['image'],
                (int) $row['views'],
                new \DateTimeImmutable((string) $row['published_at'], new \DateTimeZone('UTC')),
            ),
            $statement->fetchAll(),
        );
    }

    /**
     * Единственное место, где порядок из перечисления превращается в SQL.
     *
     * Сюда не может попасть строка из запроса пользователя: на входе
     * перечисление, у match нет ветки по умолчанию, и добавление нового
     * варианта сортировки сломает сборку, а не превратится в тихую ошибку.
     *
     * Вторым столбцом везде id: без него две статьи с одинаковой датой или
     * одинаковым числом просмотров могут прийти в разном порядке на разных
     * страницах — одна покажется дважды, другая пропадёт.
     */
    private static function orderBy(PostSort $sort): string
    {
        return match ($sort) {
            PostSort::Newest => 'p.published_at DESC, p.id DESC',
            PostSort::Popular => 'p.views DESC, p.id DESC',
        };
    }
}
