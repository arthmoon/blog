<?php

declare(strict_types=1);

use App\Blog\Application\Port\CategoryPostsQuery;
use App\Blog\Application\Port\CategoryQuery;
use App\Blog\Application\Port\HomePageQuery;
use App\Blog\Application\Port\PostQuery;
use App\Blog\Application\Port\SimilarPostsQuery;
use App\Blog\Domain\Repository\CategoryRepositoryInterface;
use App\Blog\Domain\Repository\PostRepositoryInterface;
use App\Blog\Infrastructure\Event\BlogListeners;
use App\Blog\Infrastructure\Event\DomainEventDispatcher;
use App\Blog\Infrastructure\Event\ListenerDomainEventDispatcher;
use App\Blog\Infrastructure\Persistence\Mysql\PdoCategoryPostsQuery;
use App\Blog\Infrastructure\Persistence\Mysql\PdoCategoryQuery;
use App\Blog\Infrastructure\Persistence\Mysql\PdoCategoryRepository;
use App\Blog\Infrastructure\Persistence\Mysql\PdoHomePageQuery;
use App\Blog\Infrastructure\Persistence\Mysql\PdoPostQuery;
use App\Blog\Infrastructure\Persistence\Mysql\PdoPostRepository;
use App\Blog\Infrastructure\Persistence\Mysql\PdoSimilarPostsQuery;
use App\Blog\Infrastructure\Projection\CategoryLatestPostsProjector;
use App\Shared\Infrastructure\Config\DatabaseConfig;
use App\Shared\Infrastructure\Container\Container;
use App\Shared\Infrastructure\Database\ConnectionFactory;
use App\Shared\Infrastructure\Database\TransactionManager;
use App\Shared\Infrastructure\Http\Kernel;
use App\Shared\Infrastructure\Http\Router;
use App\Shared\Infrastructure\Log\FileLogger;
use Psr\Log\LoggerInterface;

/**
 * Сборка приложения.
 *
 * Весь граф зависимостей описан здесь и читается сверху вниз: соединение,
 * события, репозитории, читающие запросы, веб. Автосвязывания нет намеренно —
 * так видно, что во что подставляется, без чтения конструкторов.
 *
 * Маршруты пока не зарегистрированы: контроллеры появятся следующими шагами,
 * и до тех пор приложение честно отвечает 404 на любой адрес.
 */
return static function (Container $container, string $root): void {
    $container->set('debug', static fn (): bool => '1' === getenv('APP_DEBUG'));

    $container->set(LoggerInterface::class, static fn (): LoggerInterface => new FileLogger($root . '/var/log/app.log'));

    $container->set(\PDO::class, static fn (): \PDO => (new ConnectionFactory(DatabaseConfig::fromEnvironment()))->create());

    $container->set(TransactionManager::class, static fn (Container $c): TransactionManager => new TransactionManager($c->get(\PDO::class)));

    $container->set(CategoryLatestPostsProjector::class, static fn (Container $c): CategoryLatestPostsProjector => new CategoryLatestPostsProjector($c->get(\PDO::class)));

    $container->set(DomainEventDispatcher::class, static function (Container $c): DomainEventDispatcher {
        $dispatcher = new ListenerDomainEventDispatcher();
        BlogListeners::register($dispatcher, $c->get(CategoryLatestPostsProjector::class));

        return $dispatcher;
    });

    // Запись
    $container->set(CategoryRepositoryInterface::class, static fn (Container $c): CategoryRepositoryInterface => new PdoCategoryRepository($c->get(\PDO::class)));

    $container->set(PostRepositoryInterface::class, static fn (Container $c): PostRepositoryInterface => new PdoPostRepository(
        $c->get(\PDO::class),
        $c->get(TransactionManager::class),
        $c->get(DomainEventDispatcher::class),
    ));

    // Чтение
    $container->set(HomePageQuery::class, static fn (Container $c): HomePageQuery => new PdoHomePageQuery($c->get(\PDO::class)));
    $container->set(CategoryQuery::class, static fn (Container $c): CategoryQuery => new PdoCategoryQuery($c->get(\PDO::class)));
    $container->set(CategoryPostsQuery::class, static fn (Container $c): CategoryPostsQuery => new PdoCategoryPostsQuery($c->get(\PDO::class)));
    $container->set(PostQuery::class, static fn (Container $c): PostQuery => new PdoPostQuery($c->get(\PDO::class)));
    $container->set(SimilarPostsQuery::class, static fn (Container $c): SimilarPostsQuery => new PdoSimilarPostsQuery($c->get(\PDO::class)));

    // Веб
    $container->set(Router::class, static function (): Router {
        return new Router();
    });

    $container->set(Kernel::class, static fn (Container $c): Kernel => new Kernel(
        $c,
        $c->get(Router::class),
        $c->get(LoggerInterface::class),
        $c->get('debug'),
    ));
};
