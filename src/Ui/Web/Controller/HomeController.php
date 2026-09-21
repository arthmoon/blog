<?php

declare(strict_types=1);

namespace App\Ui\Web\Controller;

use App\Blog\Application\Port\HomePageQuery;
use App\Shared\Infrastructure\Http\Request;
use App\Shared\Infrastructure\Http\Response;
use App\Shared\Infrastructure\Template\TemplateRenderer;

/**
 * Главная страница: категории с опубликованными статьями и три последних
 * поста в каждой.
 *
 * Контроллер получился в три строки, и это норма: вся работа сделана
 * запросом, который отдаёт готовые read-модели. Ни фильтрации, ни
 * сортировки, ни обхода коллекций здесь быть не должно.
 */
final readonly class HomeController
{
    private const int POSTS_PER_CATEGORY = 3;

    public function __construct(
        private HomePageQuery $query,
        private TemplateRenderer $templates,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        return Response::html($this->templates->render('home.tpl', [
            'sections' => $this->query->categoriesWithLatestPosts(self::POSTS_PER_CATEGORY),
        ]));
    }
}
