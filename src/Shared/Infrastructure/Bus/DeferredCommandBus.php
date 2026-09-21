<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus;

use Psr\Log\LoggerInterface;

/**
 * Очередь команд, выполняемых после отправки ответа.
 *
 * Контроллер кладёт сюда команду и ничего не знает ни про очередь, ни про
 * то, что выполнение отложено. Именно поэтому в очередь идут команды,
 * а не замыкания: замыкание протащило бы в контроллер и зависимости
 * обработчика, и знание о моменте вызова.
 *
 * Отсутствие обработчика проверяется при добавлении, а не при выполнении.
 * Это ошибка в сборке приложения, и узнать о ней надо до того, как ответ
 * уйдёт клиенту, — иначе она проявится в логе на проде и нигде больше.
 *
 * А вот падение самого обработчика наружу не выпускается: ответ уже у
 * клиента, показать ошибку некому, и непойманное исключение после
 * fastcgi_finish_request превратилось бы в фатальную ошибку в логах
 * веб-сервера. Пишем в лог и продолжаем с остальными командами.
 */
final class DeferredCommandBus
{
    /** @var array<class-string, callable(object): void> */
    private array $handlers = [];

    /** @var list<object> */
    private array $queue = [];

    /**
     * @param bool $deferred false — выполнять сразу. Так шина работает
     *                       в консоли и в тестах, где отправлять нечего.
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $deferred = true,
    ) {
    }

    /**
     * @param class-string             $commandClass
     * @param callable(covariant object): void $handler
     */
    public function register(string $commandClass, callable $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    public function push(object $command): void
    {
        if (!isset($this->handlers[$command::class])) {
            throw new \LogicException(sprintf('Для команды %s не зарегистрирован обработчик.', $command::class));
        }

        if (!$this->deferred) {
            $this->execute($command);

            return;
        }

        $this->queue[] = $command;
    }

    /**
     * Выполняет накопленное. Вызывается ядром после отправки ответа.
     */
    public function flush(): void
    {
        $commands = $this->queue;
        $this->queue = [];

        foreach ($commands as $command) {
            $this->execute($command);
        }
    }

    public function pending(): int
    {
        return \count($this->queue);
    }

    private function execute(object $command): void
    {
        try {
            ($this->handlers[$command::class])($command);
        } catch (\Throwable $e) {
            $this->logger->error('Отложенная команда {command} не выполнилась: {message}', [
                'command' => $command::class,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
