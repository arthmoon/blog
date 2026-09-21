<?php

declare(strict_types=1);

namespace App\Blog\Application\Port;

use App\Blog\Application\ReadModel\CategoryWithPosts;

/**
 * Главная страница: категории, в которых есть опубликованные статьи,
 * и несколько последних статей в каждой.
 *
 * Возвращается всё одним вызовом, потому что реализация обязана уложиться
 * в один запрос к базе. Метод «дай категории» плюс метод «дай статьи
 * категории» неизбежно превратились бы в N+1 на стороне вызывающего.
 */
interface HomePageQuery
{
    /**
     * @return list<CategoryWithPosts>
     */
    public function categoriesWithLatestPosts(int $postsPerCategory): array;
}
