<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Persistence\Mysql;

use App\Blog\Application\Port\HomePageQuery;
use App\Blog\Application\ReadModel\CategoryRef;
use App\Blog\Application\ReadModel\CategoryWithPosts;
use App\Blog\Application\ReadModel\PostListItem;
use App\Blog\Domain\ValueObject\ImagePath;

/**
 * Главная страница читается из проекции category_latest_posts.
 *
 * Один запрос на всю страницу: проекция уже хранит, какие статьи и в каком
 * порядке показывать, поэтому оконная функция здесь не нужна — она отработала
 * в момент публикации. Категории без опубликованных статей отсутствуют в
 * проекции и потому не попадают в выдачу сами собой, без отдельного условия.
 *
 * Порядок категорий — по названию. Задание его не задаёт, а алфавит
 * предсказуем для читателя и устойчив между запросами.
 */
final readonly class PdoHomePageQuery implements HomePageQuery
{
    public function __construct(private \PDO $connection)
    {
    }

    public function categoriesWithLatestPosts(int $postsPerCategory): array
    {
        if ($postsPerCategory < 1) {
            throw new \InvalidArgumentException(sprintf('Статей на категорию должно быть хотя бы одна, запрошено %d.', $postsPerCategory));
        }

        $sql = <<<'SQL'
            SELECT c.id           AS category_id,
                   c.title        AS category_title,
                   c.slug         AS category_slug,
                   p.title        AS post_title,
                   p.slug         AS post_slug,
                   p.description  AS post_description,
                   p.image        AS post_image,
                   p.views        AS post_views,
                   p.published_at AS post_published_at
              FROM category_latest_posts f
              JOIN categories c ON c.id = f.category_id
              JOIN posts      p ON p.id = f.post_id
             WHERE f.position <= :posts_per_category
             ORDER BY c.title, f.position
            SQL;

        $statement = $this->connection->prepare($sql);
        $statement->bindValue('posts_per_category', $postsPerCategory, \PDO::PARAM_INT);
        $statement->execute();

        return $this->group($statement->fetchAll());
    }

    /**
     * Строки приходят уже отсортированными, поэтому группировка — один проход.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<CategoryWithPosts>
     */
    private function group(array $rows): array
    {
        /** @var array<int, array{category: CategoryRef, posts: list<PostListItem>}> $grouped */
        $grouped = [];

        foreach ($rows as $row) {
            $categoryId = (int) $row['category_id'];

            $grouped[$categoryId] ??= [
                'category' => new CategoryRef((string) $row['category_title'], (string) $row['category_slug']),
                'posts' => [],
            ];

            $grouped[$categoryId]['posts'][] = new PostListItem(
                (string) $row['post_title'],
                (string) $row['post_slug'],
                (string) $row['post_description'],
                self::imageUrl($row['post_image']),
                (int) $row['post_views'],
                new \DateTimeImmutable((string) $row['post_published_at'], new \DateTimeZone('UTC')),
            );
        }

        return array_values(array_map(
            static fn (array $section): CategoryWithPosts => new CategoryWithPosts($section['category'], $section['posts']),
            $grouped,
        ));
    }

    private static function imageUrl(mixed $image): ?string
    {
        return null === $image ? null : ImagePath::PUBLIC_PREFIX . (string) $image;
    }
}
