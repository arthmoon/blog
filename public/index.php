<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Container\Container;
use App\Shared\Infrastructure\Error\FatalErrorHandler;
use App\Shared\Infrastructure\Http\Kernel;
use App\Shared\Infrastructure\Http\Request;
use App\Shared\Infrastructure\Http\ResponseSender;
use Psr\Log\LoggerInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

$container = new Container();
(require $root . '/config/services.php')($container, $root);

// Регистрируется до всего остального: ошибка при сборке контейнера
// тоже должна попасть в лог.
(new FatalErrorHandler($container->get(LoggerInterface::class), $container->get('debug')))->register();

$kernel = $container->get(Kernel::class);

$response = $kernel->handle(Request::fromGlobals());

// Три шага, и порядок принципиален: собрали ответ, отдали клиенту,
// доделали остальное уже без него.
$container->get(ResponseSender::class)->send($response);
$kernel->terminate();
