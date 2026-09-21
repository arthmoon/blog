<?php

declare(strict_types=1);

namespace App\Blog\Application\Port;

use App\Blog\Application\ReadModel\CategoryView;
use App\Blog\Domain\ValueObject\Slug;

/**
 * Поиск категории по адресу страницы.
 */
interface CategoryQuery
{
    /**
     * null означает «такой категории нет» — контроллер отвечает 404.
     * Исключение здесь было бы потоком управления, а не ошибкой.
     */
    public function findBySlug(Slug $slug): ?CategoryView;
}
