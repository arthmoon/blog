<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

/**
 * Входящий запрос.
 *
 * Суперглобальные массивы читаются ровно здесь, в одном месте. Дальше по
 * коду ходит объект, поэтому контроллер можно вызвать в тесте без веб-сервера
 * и без подмены $_GET.
 */
final readonly class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, string> $attributes параметры, извлечённые роутером из адреса
     */
    public function __construct(
        public string $method,
        public string $path,
        private array $query = [],
        private array $attributes = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        /** @var array<string, string> $query */
        $query = array_filter($_GET, 'is_string');

        return new self(
            \is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            self::pathFrom(\is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/'),
            $query,
        );
    }

    /**
     * @param array<string, string> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return new self($this->method, $this->path, $this->query, $attributes);
    }

    public function attribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    public function query(string $name): ?string
    {
        return $this->query[$name] ?? null;
    }

    /**
     * Мусор в параметре не ошибка, а обычное дело: ссылку могли исказить
     * при копировании. Возвращаем значение по умолчанию вместо 400.
     */
    public function queryInt(string $name, int $default): int
    {
        $value = filter_var($this->query[$name] ?? null, \FILTER_VALIDATE_INT);

        return false === $value ? $default : $value;
    }

    private static function pathFrom(string $uri): string
    {
        $path = parse_url($uri, \PHP_URL_PATH);

        return \is_string($path) && '' !== $path ? $path : '/';
    }
}
