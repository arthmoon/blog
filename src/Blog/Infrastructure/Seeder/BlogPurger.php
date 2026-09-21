<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Seeder;

/**
 * Очистка блога перед повторным наполнением.
 *
 * Живёт в инфраструктуре, а не в слое приложения: «удалить всё» нужно
 * ровно одному инструменту разработчика, и в портах домена такому методу
 * делать нечего.
 */
final readonly class BlogPurger
{
    /** Порядок не важен при выключенных проверках, но читается понятнее сверху вниз. */
    private const array TABLES = ['category_latest_posts', 'post_category', 'posts', 'categories'];

    public function __construct(private \PDO $connection)
    {
    }

    public function purge(): void
    {
        $this->connection->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach (self::TABLES as $table) {
                $this->connection->exec('TRUNCATE TABLE ' . $table);
            }
        } finally {
            $this->connection->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
