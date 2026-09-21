<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

/**
 * Ответ, который ещё не отправлен.
 *
 * Контроллер возвращает объект, а не печатает в вывод: иначе его нельзя
 * было бы проверить тестом и невозможно было бы решить, что отправлять,
 * уже после того, как тело собрано.
 */
final readonly class Response
{
    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        public int $status,
        public string $body,
        public array $headers,
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function notFound(string $body): self
    {
        return self::html($body, 404);
    }

    public static function serverError(string $body): self
    {
        return self::html($body, 500);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        echo $this->body;
    }
}
