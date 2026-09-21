<?php

declare(strict_types=1);

namespace App\Ui\Web;

use App\Blog\Domain\ValueObject\Slug;
use App\Shared\Infrastructure\Http\PageNotFound;
use App\Shared\Infrastructure\Http\Request;

/**
 * Слаг из адреса.
 *
 * Правило «кривой слаг — это несуществующая страница, а не ошибка сервера»
 * должно быть одно на все страницы, иначе однажды один из контроллеров
 * отдаст 500 там, где остальные отдают 404.
 */
final readonly class SlugParameter
{
    public static function of(Request $request, string $name = 'slug'): Slug
    {
        try {
            return Slug::fromString((string) $request->attribute($name));
        } catch (\InvalidArgumentException) {
            throw new PageNotFound();
        }
    }
}
