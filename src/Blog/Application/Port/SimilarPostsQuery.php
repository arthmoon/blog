<?php

declare(strict_types=1);

namespace App\Blog\Application\Port;

use App\Blog\Application\ReadModel\PostListItem;
use App\Blog\Domain\ValueObject\PostId;

/**
 * Похожие статьи.
 *
 * Критерий в задании не задан, поэтому он наш: чем больше общих категорий,
 * тем выше релевантность, при равенстве выигрывает свежая. Если похожих
 * меньше запрошенного, список добирается свежими статьями тех же категорий,
 * чтобы блок не выглядел сломанным.
 */
interface SimilarPostsQuery
{
    /**
     * @return list<PostListItem>
     */
    public function forPost(PostId $postId, int $limit): array;
}
