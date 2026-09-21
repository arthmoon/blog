<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Persistence\Mysql;

use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\PostRepositoryInterface;
use App\Blog\Domain\ValueObject\PostId;
use App\Blog\Infrastructure\Event\DomainEventDispatcher;
use App\Shared\Infrastructure\Database\TransactionManager;

final readonly class PdoPostRepository implements PostRepositoryInterface
{
    public function __construct(
        private \PDO $connection,
        private TransactionManager $transactions,
        private DomainEventDispatcher $events,
    ) {
    }

    /**
     * Строка статьи, её категории и перестройка проекции — одной транзакцией.
     *
     * События забираются после записи, но до коммита: подписчик обновляет
     * проекцию, и она обязана меняться вместе с данными. Если подписчик
     * упадёт, откатится всё, включая саму статью, — это и нужно.
     */
    public function save(Post $post): void
    {
        $this->transactions->transactional(function () use ($post): void {
            $post->isNew() ? $this->insert($post) : $this->update($post);

            $this->syncCategories($post);

            $this->events->dispatch($post->releaseEvents());
        });
    }

    public function incrementViews(PostId $postId): void
    {
        // Без транзакции и без чтения: одно выражение, которое не теряет
        // параллельные показы. Отдельная транзакция здесь только добавила
        // бы две лишние команды на каждый просмотр.
        $this->connection
            ->prepare('UPDATE posts SET views = views + 1 WHERE id = :id')
            ->execute(['id' => $postId->value]);
    }

    private function insert(Post $post): void
    {
        $sql = <<<'SQL'
            INSERT INTO posts (title, slug, description, body, image, views, published_at)
            VALUES (:title, :slug, :description, :body, :image, :views, :published_at)
            SQL;

        $this->connection->prepare($sql)->execute([
            'title' => $post->title(),
            'slug' => $post->slug()->value,
            'description' => $post->description(),
            'body' => $post->body(),
            'image' => $post->image()?->value,
            'views' => $post->views()->value,
            'published_at' => self::formatDate($post->publishedAt()),
        ]);

        $post->assignId(PostId::fromInt((int) $this->connection->lastInsertId()));
    }

    /**
     * views здесь отсутствует намеренно.
     *
     * Счётчик живёт своей жизнью через атомарный инкремент, и если писать
     * его при каждом обновлении статьи, то значение, прочитанное минуту
     * назад, затёрло бы все просмотры, случившиеся с тех пор.
     */
    private function update(Post $post): void
    {
        $sql = <<<'SQL'
            UPDATE posts
               SET title = :title,
                   slug = :slug,
                   description = :description,
                   body = :body,
                   image = :image,
                   published_at = :published_at
             WHERE id = :id
            SQL;

        $this->connection->prepare($sql)->execute([
            'title' => $post->title(),
            'slug' => $post->slug()->value,
            'description' => $post->description(),
            'body' => $post->body(),
            'image' => $post->image()?->value,
            'published_at' => self::formatDate($post->publishedAt()),
            'id' => $post->id()?->value,
        ]);
    }

    /**
     * Связи переписываются целиком: удалить и вставить заново.
     *
     * Вычислять разницу было бы экономнее, но категорий у статьи единицы,
     * а кода на сравнение наборов — заметно больше. Всё внутри транзакции,
     * так что промежуточного состояния никто не увидит.
     */
    private function syncCategories(Post $post): void
    {
        $postId = $post->id()?->value;

        if (null === $postId) {
            throw new \LogicException('Статья без идентификатора не может иметь связей.');
        }

        $this->connection
            ->prepare('DELETE FROM post_category WHERE post_id = :post_id')
            ->execute(['post_id' => $postId]);

        $insert = $this->connection->prepare(
            'INSERT INTO post_category (post_id, category_id) VALUES (:post_id, :category_id)',
        );

        foreach ($post->categoryIds() as $categoryId) {
            $insert->execute([
                'post_id' => $postId,
                'category_id' => $categoryId->value,
            ]);
        }
    }

    /**
     * Дата приводится к UTC явно: соединение работает в UTC, и статья,
     * созданная с датой в другом поясе, иначе сохранилась бы со сдвигом.
     */
    private static function formatDate(?\DateTimeImmutable $date): ?string
    {
        return $date?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
