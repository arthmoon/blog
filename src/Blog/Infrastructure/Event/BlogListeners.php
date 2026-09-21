<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Event;

use App\Blog\Domain\Event\PostCategoriesChanged;
use App\Blog\Domain\Event\PostPublished;
use App\Blog\Domain\Event\PostUnpublished;
use App\Blog\Infrastructure\Projection\CategoryLatestPostsProjector;

/**
 * Подписки блога в одном месте.
 *
 * Собраны отдельно от контейнера, чтобы ответ на вопрос «кто и на что
 * реагирует» читался одним экраном, а не собирался по определениям сервисов.
 */
final class BlogListeners
{
    public static function register(
        ListenerDomainEventDispatcher $dispatcher,
        CategoryLatestPostsProjector $projector,
    ): void {
        $dispatcher->subscribe(
            PostPublished::class,
            static fn (PostPublished $event) => $projector->onPostPublished($event),
        );

        $dispatcher->subscribe(
            PostUnpublished::class,
            static fn (PostUnpublished $event) => $projector->onPostUnpublished($event),
        );

        $dispatcher->subscribe(
            PostCategoriesChanged::class,
            static fn (PostCategoriesChanged $event) => $projector->onPostCategoriesChanged($event),
        );
    }
}
