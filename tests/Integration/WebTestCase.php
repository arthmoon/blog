<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Shared\Infrastructure\Container\Container;
use App\Shared\Infrastructure\Http\Kernel;
use App\Shared\Infrastructure\Http\Request;
use App\Shared\Infrastructure\Http\Response;

/**
 * Запрос проходит через настоящее приложение: тот же контейнер, тот же
 * роутер, тот же Smarty, та же база.
 *
 * Собирается из боевого config/services.php, а не из отдельной тестовой
 * сборки: иначе тесты проверяли бы конфигурацию, которой нет в бою.
 * Подменяется ровно одно — соединение с базой, чтобы тест и приложение
 * работали с одними данными.
 */
abstract class WebTestCase extends IntegrationTestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $root = \dirname(__DIR__, 2);

        $this->container = new Container();
        (require $root . '/config/services.php')($this->container, $root);

        $this->container->set(\PDO::class, fn (): \PDO => $this->connection);
    }

    /**
     * @param array<string, string> $query
     */
    protected function get(string $path, array $query = []): Response
    {
        return $this->container->get(Kernel::class)->handle(new Request('GET', $path, $query));
    }

    /**
     * То, что происходит уже после отправки ответа. В вебе это вызывает
     * public/index.php; в тесте — сам тест, чтобы проверить порядок.
     */
    protected function terminate(): void
    {
        $this->container->get(Kernel::class)->terminate();
    }
}
