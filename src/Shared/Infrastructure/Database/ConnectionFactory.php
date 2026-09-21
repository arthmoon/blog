<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

use App\Shared\Infrastructure\Config\DatabaseConfig;

/**
 * Создаёт соединение с MySQL.
 *
 * Значения атрибутов здесь не декоративные:
 *
 * ERRMODE_EXCEPTION — иначе ошибки запроса пришлось бы проверять после
 * каждого вызова, и первая же забытая проверка проглотила бы сбой.
 *
 * EMULATE_PREPARES = false — подготовку делает сервер, а не драйвер.
 * Это настоящие подготовленные выражения, а не подстановка строк, и заодно
 * LIMIT принимает целочисленный параметр.
 *
 * STRINGIFY_FETCHES = false вместе с выключенной эмуляцией даёт числа
 * как int, а не как строки: без этого views приходил бы как "42",
 * и типизированные read-модели пришлось бы кормить приведениями.
 */
final readonly class ConnectionFactory
{
    public function __construct(private DatabaseConfig $config)
    {
    }

    public function create(): \PDO
    {
        $connection = new \PDO(
            $this->config->dsn(),
            $this->config->user,
            $this->config->password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );

        // Время сессии фиксируем в UTC. Даты публикации сравниваются
        // с NOW() на стороне базы, и зависеть от часового пояса контейнера
        // здесь нельзя: тот же запрос в другом окружении отдавал бы
        // другой набор статей.
        $connection->exec("SET time_zone = '+00:00'");

        return $connection;
    }
}
