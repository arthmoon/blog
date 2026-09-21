<?php

declare(strict_types=1);

namespace App\Blog\Application;

/**
 * Порядок статей в списке категории.
 *
 * Перечисление вместо строки из запроса решает сразу две задачи: подстановка
 * произвольного текста в ORDER BY становится невозможной по построению,
 * а не потому что кто-то не забыл проверить, и набор допустимых значений
 * виден в одном месте.
 *
 * Сами фрагменты ORDER BY живут в реализации запроса: SQL — дело
 * инфраструктуры, слой приложения о нём знать не должен.
 */
enum PostSort: string
{
    case Newest = 'date';
    case Popular = 'views';

    public static function default(): self
    {
        return self::Newest;
    }

    /**
     * Значение из строки запроса. Мусор и отсутствие параметра одинаково
     * дают порядок по умолчанию — для списка статей это безопаснее, чем 400.
     */
    public static function fromQueryString(?string $value): self
    {
        if (null === $value) {
            return self::default();
        }

        return self::tryFrom($value) ?? self::default();
    }
}
