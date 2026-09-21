<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Shared\Infrastructure\Config\DatabaseConfig;
use App\Shared\Infrastructure\Database\ConnectionFactory;
use App\Shared\Infrastructure\Database\Migrator;
use App\Tests\Support\BlogFixtures;
use PHPUnit\Framework\TestCase;

/**
 * Общее основание для тестов, которым нужна настоящая MySQL.
 *
 * Соединение и миграции — один раз на прогон, очистка таблиц — перед каждым
 * тестом. Транзакция с откатом выглядела бы аккуратнее, но проверять
 * приходится как раз код, который сам управляет транзакциями, и внешняя
 * обёртка исказила бы картину.
 */
abstract class IntegrationTestCase extends TestCase
{
    /** Порядок важен: сначала зависимые таблицы. */
    private const array TABLES = ['category_latest_posts', 'post_category', 'posts', 'categories'];

    private static ?\PDO $sharedConnection = null;

    protected \PDO $connection;

    protected BlogFixtures $fixtures;

    protected function setUp(): void
    {
        $this->connection = self::connection();
        $this->fixtures = new BlogFixtures($this->connection);

        $this->truncateAll();
    }

    protected static function connection(): \PDO
    {
        if (null !== self::$sharedConnection) {
            return self::$sharedConnection;
        }

        $config = DatabaseConfig::fromEnvironment();

        // Предохранитель: тесты чистят таблицы целиком, и запуск против
        // рабочей базы стёр бы данные разработчика.
        if (!str_ends_with($config->database, '_test')) {
            throw new \RuntimeException(sprintf(
                'Интеграционные тесты работают только с базой, имя которой оканчивается на _test. Получено «%s».',
                $config->database,
            ));
        }

        $connection = (new ConnectionFactory($config))->create();
        (new Migrator($connection, \dirname(__DIR__, 2) . '/database/migrations'))->migrate();

        return self::$sharedConnection = $connection;
    }

    private function truncateAll(): void
    {
        $this->connection->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::TABLES as $table) {
            $this->connection->exec('TRUNCATE TABLE ' . $table);
        }

        $this->connection->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
