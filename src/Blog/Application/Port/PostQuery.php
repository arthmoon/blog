<?php

declare(strict_types=1);

namespace App\Blog\Application\Port;

use App\Blog\Application\ReadModel\PostDetail;
use App\Blog\Domain\ValueObject\Slug;

/**
 * Страница статьи.
 */
interface PostQuery
{
    public function findBySlug(Slug $slug): ?PostDetail;
}
