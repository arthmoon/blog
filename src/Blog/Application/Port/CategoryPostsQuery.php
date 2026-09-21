<?php

declare(strict_types=1);

namespace App\Blog\Application\Port;

use App\Blog\Application\PostSort;
use App\Blog\Application\ReadModel\PostsPage;
use App\Blog\Domain\ValueObject\CategoryId;

/**
 * Страница статей категории с сортировкой и постраничной навигацией.
 */
interface CategoryPostsQuery
{
    public function page(CategoryId $categoryId, PostSort $sort, int $page, int $perPage): PostsPage;
}
