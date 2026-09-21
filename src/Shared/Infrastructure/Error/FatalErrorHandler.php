<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Error;

use Psr\Log\LoggerInterface;

/**
 * Перехват фатальных ошибок, которые не видит try/catch.
 *
 * Исчерпание памяти и превышение max_execution_time — это E_ERROR: ни
 * catch (\Throwable), ни set_exception_handler до них не доберутся. Остаётся
 * register_shutdown_function вместе с error_get_last().
 *
 * Тонкость, ради которой этот класс и написан: когда память кончилась,
 * обработчику самому нужна память, чтобы собрать строку и открыть файл.
 * Поэтому на старте резервируется буфер, и первое, что делает обработчик, —
 * освобождает его. Без этого лог остался бы пустым ровно в том случае,
 * когда он нужнее всего.
 */
final class FatalErrorHandler
{
    private const int FATAL_LEVELS = \E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR;

    private const int RESERVE_BYTES = 262144;

    private ?string $reserve = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $debug = false,
    ) {
    }

    public function register(): void
    {
        $this->reserve = str_repeat(' ', self::RESERVE_BYTES);

        register_shutdown_function($this->onShutdown(...));
    }

    /**
     * Публичный, потому что вызывается как обработчик завершения;
     * в тестах его удобно дёрнуть напрямую.
     */
    public function onShutdown(): void
    {
        $this->reserve = null;

        $error = error_get_last();

        if (null === $error || 0 === ($error['type'] & self::FATAL_LEVELS)) {
            return;
        }

        $this->logger->critical('Фатальная ошибка: {message}', [
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
            'type' => $error['type'],
        ]);

        // Если ответ уже ушёл клиенту — а после fastcgi_finish_request это
        // так, — показать страницу ошибки уже нельзя. Остаётся запись в лог.
        if (headers_sent()) {
            return;
        }

        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');

        echo $this->debug
            ? sprintf(
                "<h1>Фатальная ошибка</h1><pre>%s\n%s:%d</pre>",
                htmlspecialchars($error['message'], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($error['file'], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
                $error['line'],
            )
            : '<h1>Внутренняя ошибка</h1><p>Мы уже знаем о проблеме.</p>';
    }
}
