<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

/**
 * Сопоставление адреса с контроллером.
 *
 * Шаблон вида /category/{slug} компилируется в регулярное выражение один
 * раз при регистрации. Постоянные куски проходят через preg_quote, так что
 * точка или плюс в адресе останутся собой, а не превратятся в метасимвол.
 *
 * Метод запроса участвует в сопоставлении, но несовпадение метода даёт то же
 * «не найдено», а не 405: блог отвечает только на GET, и различать эти два
 * случая пока некому.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: class-string}> */
    private array $routes = [];

    /**
     * @param class-string $handler
     */
    public function get(string $pattern, string $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    /**
     * @param class-string $handler
     */
    public function add(string $method, string $pattern, string $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => self::compile($pattern),
            'handler' => $handler,
        ];
    }

    public function match(Request $request): ?Route
    {
        $path = self::normalize($request->path);
        $method = strtoupper($request->method);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (1 !== preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            /** @var array<string, string> $parameters */
            $parameters = array_filter(
                $matches,
                static fn (int|string $key): bool => \is_string($key),
                \ARRAY_FILTER_USE_KEY,
            );

            return new Route($route['handler'], $parameters);
        }

        return null;
    }

    /**
     * /category/news/ и /category/news — одна и та же страница. Иначе
     * лишний слеш в ссылке из письма давал бы 404, а поисковик увидел бы
     * два адреса с одинаковым содержимым.
     */
    private static function normalize(string $path): string
    {
        $trimmed = rtrim($path, '/');

        return '' === $trimmed ? '/' : $trimmed;
    }

    private static function compile(string $pattern): string
    {
        $parts = preg_split('#(\{\w+\})#', self::normalize($pattern), -1, \PREG_SPLIT_DELIM_CAPTURE);

        if (false === $parts) {
            throw new \InvalidArgumentException(sprintf('Не удалось разобрать шаблон «%s».', $pattern));
        }

        $regex = '';

        foreach ($parts as $part) {
            if (1 === preg_match('#^\{(\w+)\}$#', $part, $name)) {
                // Параметр не может содержать слеш: /posts/a/b не должен
                // совпадать с /posts/{slug}.
                $regex .= '(?P<' . $name[1] . '>[^/]+)';

                continue;
            }

            $regex .= preg_quote($part, '#');
        }

        return '#^' . $regex . '$#u';
    }
}
