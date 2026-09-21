<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Bus\DeferredCommandBus;
use App\Shared\Infrastructure\Container\Container;
use App\Shared\Infrastructure\Template\TemplateRenderer;
use Psr\Log\LoggerInterface;

/**
 * Превращает запрос в ответ.
 *
 * Здесь же единственное место, где ловятся исключения: контроллеры пишутся
 * так, будто всё хорошо, а разбор неудач собран в одном экране.
 *
 * Запрос обрабатывается в два приёма. handle() собирает ответ, дальше ответ
 * уходит клиенту, и только потом terminate() доделывает то, что читателю
 * ждать незачем, — сейчас это единственная запись на странице чтения,
 * счётчик просмотров.
 */
final readonly class Kernel
{
    public function __construct(
        private Container $container,
        private Router $router,
        private DeferredCommandBus $deferred,
        private TemplateRenderer $templates,
        private LoggerInterface $logger,
        private bool $debug = false,
        private int $demonstrationDelay = 0,
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

    /**
     * Выполняется после того, как ответ отправлен.
     *
     * Ошибок наружу не выпускает: показывать их уже некому, а исключение
     * на этой стадии обернулось бы фатальной ошибкой в логах веб-сервера.
     * Шина сама пишет о сбоях обработчиков, здесь остаётся подстраховка
     * на случай, если сломается она сама.
     */
    public function terminate(): void
    {
        try {
            // Задержка для наглядной проверки: при DEFERRED_DEMO_SLEEP=2
            // страница всё равно открывается мгновенно, а счётчик растёт
            // через две секунды. По умолчанию ноль и ничего не делает.
            if ($this->demonstrationDelay > 0) {
                sleep($this->demonstrationDelay);
            }

            $this->deferred->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Сбой при завершении запроса: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
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

    private function notFound(): Response
    {
        return Response::notFound($this->renderError('error/404.tpl'));
    }

    private function serverError(\Throwable $e): Response
    {
        return Response::serverError($this->renderError('error/500.tpl', [
            'debug' => $this->debug,
            'exception' => $this->debug ? [
                'class' => $e::class,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ] : null,
        ]));
    }

    /**
     * Если сломан сам шаблонизатор, страница ошибки не должна вызывать
     * вторую ошибку. Поэтому запасной вариант без шаблонов — простая
     * разметка строкой.
     *
     * @param array<string, mixed> $data
     */
    private function renderError(string $template, array $data = []): string
    {
        try {
            return $this->templates->render($template, $data);
        } catch (\Throwable $e) {
            $this->logger->error('Не удалось отрисовать страницу ошибки: {message}', [
                'message' => $e->getMessage(),
                'template' => $template,
            ]);

            return '<!doctype html><meta charset="utf-8"><title>Ошибка</title>'
                . '<h1>Что-то пошло не так</h1><p><a href="/">На главную</a></p>';
        }
    }
}
