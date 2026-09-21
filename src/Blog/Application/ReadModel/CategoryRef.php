<?php

declare(strict_types=1);

namespace App\Blog\Application\ReadModel;

/**
 * Категория в виде ссылки: всё, что нужно для заголовка секции на главной
 * или для подписи под статьёй. Описание сюда не попадает — на этих экранах
 * оно не выводится.
 */
final readonly class CategoryRef
{
    public function __construct(
        public string $title,
        public string $slug,
    ) {
    }
}
