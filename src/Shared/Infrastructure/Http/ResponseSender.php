<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

/**
 * Отправка ответа и освобождение клиента.
 *
 * Здесь важно различать две вещи, которые часто путают.
 * register_shutdown_function() лишь откладывает код до конца скрипта —
 * браузер всё это время продолжает ждать, потому что процесс не отпустил
 * соединение. Отпускает его только fastcgi_finish_request(): ответ уходит
 * клиенту, а скрипт продолжает работать. На этом же построен
 * kernel.terminate в Symfony.
 *
 * Функция существует не везде, поэтому вызов обёрнут проверкой: в консоли
 * и под mod_php работа просто продолжится синхронно, и ничего не сломается.
 */
final class ResponseSender
{
    public function send(Response $response): void
    {
        http_response_code($response->status);

        foreach ($response->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        echo $response->body;

        $this->releaseClient();
    }

    private function releaseClient(): void
    {
        if (\function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();

            return;
        }

        if (\function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
    }
}
