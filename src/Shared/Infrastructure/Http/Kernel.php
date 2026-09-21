<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Container\Container;
use Psr\Log\LoggerInterface;

/**
 * Превращает запрос в ответ.
 *
 * Здесь же единственное место, где ловятся исключения: контроллеры пишутся
 * так, будто всё хорошо, а разбор неудач собран в одном экране.
 */
final readonly class Kernel
{
    public function __construct(
        private Container $container,
        private Router $router,
        private LoggerInterface $logger,
        private bool $debug = false,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            $route = $this->router->match($request);

            if (null === $route) {
                return $this->notFound();
            }

            return $this->run($route, $request);
        } catch (PageNotFound) {
            // Не ошибка: обычный ответ на несуществующий слаг, писать в лог нечего.
            return $this->notFound();
        } catch (\Throwable $e) {
            $this->logger->error('Необработанное исключение: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'path' => $request->path,
            ]);

            return $this->serverError($e);
        }
    }

    private function run(Route $route, Request $request): Response
    {
        $controller = $this->container->get($route->handler);

        if (!\is_callable($controller)) {
            throw new \LogicException(sprintf('Контроллер %s не вызываемый.', $route->handler));
        }

        $response = $controller($request->withAttributes($route->parameters));

        if (!$response instanceof Response) {
            throw new \LogicException(sprintf('Контроллер %s не вернул ответ.', $route->handler));
        }

        return $response;
    }

    /**
     * Оформление страниц ошибок временное: на шаге со Smarty они
     * переедут в шаблоны вместе с остальной вёрсткой.
     */
    private function notFound(): Response
    {
        return Response::notFound(
            '<!doctype html><meta charset="utf-8"><title>Страница не найдена</title>'
            . '<h1>404 — страница не найдена</h1><p><a href="/">На главную</a></p>',
        );
    }

    private function serverError(\Throwable $e): Response
    {
        if (!$this->debug) {
            return Response::serverError(
                '<!doctype html><meta charset="utf-8"><title>Ошибка</title>'
                . '<h1>500 — внутренняя ошибка</h1><p>Мы уже знаем о проблеме.</p>',
            );
        }

        return Response::serverError(sprintf(
            '<!doctype html><meta charset="utf-8"><title>Ошибка</title><h1>%s</h1><p>%s</p><pre>%s</pre>',
            self::escape($e::class),
            self::escape($e->getMessage()),
            self::escape($e->getTraceAsString()),
        ));
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
