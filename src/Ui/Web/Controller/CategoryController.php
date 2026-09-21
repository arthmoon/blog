<?php

declare(strict_types=1);

namespace App\Ui\Web\Controller;

use App\Blog\Application\Port\CategoryPostsQuery;
use App\Blog\Application\Port\CategoryQuery;
use App\Blog\Application\PostSort;
use App\Blog\Application\ReadModel\PostsPage;
use App\Blog\Domain\ValueObject\CategoryId;
use App\Blog\Domain\ValueObject\Slug;
use App\Shared\Infrastructure\Http\PageNotFound;
use App\Shared\Infrastructure\Http\Request;
use App\Shared\Infrastructure\Http\Response;
use App\Shared\Infrastructure\Template\TemplateRenderer;
use App\Ui\Web\Pagination;

/**
 * Страница категории: описание, список статей, сортировка и пагинация.
 *
 * Адреса собираются здесь, а не в шаблоне: строить query-строку в Smarty
 * значит размазать правило «сортировка по умолчанию и первая страница
 * в адрес не пишутся» по вёрстке.
 */
final readonly class CategoryController
{
    private const int POSTS_PER_PAGE = 12;

    public function __construct(
        private CategoryQuery $categories,
        private CategoryPostsQuery $posts,
        private TemplateRenderer $templates,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $category = $this->categories->findBySlug($this->slug($request));

        if (null === $category) {
            throw new PageNotFound();
        }

        $sort = PostSort::fromQueryString($request->query('sort'));
        $page = max(1, $request->queryInt('page', 1));

        $posts = $this->posts->page(CategoryId::fromInt($category->id), $sort, $page, self::POSTS_PER_PAGE);

        // Страница за последней — это 404, а не пустой список: иначе
        // поисковик проиндексирует бесконечность пустых адресов.
        if ($posts->isOutOfRange()) {
            throw new PageNotFound();
        }

        return Response::html($this->templates->render('category.tpl', [
            'category' => $category,
            'posts' => $posts,
            'sortOptions' => $this->sortOptions($category->slug, $sort),
            'pages' => $this->pages($category->slug, $sort, $posts),
            'previousUrl' => $posts->hasPrevious() ? $this->url($category->slug, $sort, $page - 1) : null,
            'nextUrl' => $posts->hasNext() ? $this->url($category->slug, $sort, $page + 1) : null,
        ]));
    }

    /**
     * Кривой слаг в адресе — это не ошибка сервера, а несуществующая
     * страница: значение объекта проверяется в конструкторе, и отказ
     * превращается в 404.
     */
    private function slug(Request $request): Slug
    {
        try {
            return Slug::fromString((string) $request->attribute('slug'));
        } catch (\InvalidArgumentException) {
            throw new PageNotFound();
        }
    }

    /**
     * @return list<array{label: string, url: string, active: bool}>
     */
    private function sortOptions(string $slug, PostSort $current): array
    {
        $labels = [
            PostSort::Newest->value => 'Сначала свежие',
            PostSort::Popular->value => 'Сначала популярные',
        ];

        return array_map(
            fn (PostSort $sort): array => [
                'label' => $labels[$sort->value],
                // Смена сортировки возвращает на первую страницу: третья
                // страница другого порядка — это другие статьи.
                'url' => $this->url($slug, $sort, 1),
                'active' => $sort === $current,
            ],
            PostSort::cases(),
        );
    }

    /**
     * @return list<array{number: int, url: string, current: bool}|null>
     */
    private function pages(string $slug, PostSort $sort, PostsPage $posts): array
    {
        if (1 === $posts->pageCount()) {
            return [];
        }

        return array_map(
            fn (?int $number): ?array => null === $number ? null : [
                'number' => $number,
                'url' => $this->url($slug, $sort, $number),
                'current' => $number === $posts->page,
            ],
            Pagination::windowed($posts->page, $posts->pageCount()),
        );
    }

    /**
     * Значения по умолчанию в адрес не пишутся: у первой страницы
     * со свежими статьями должен быть один канонический адрес.
     */
    private function url(string $slug, PostSort $sort, int $page): string
    {
        $query = [];

        if (PostSort::default() !== $sort) {
            $query['sort'] = $sort->value;
        }

        if ($page > 1) {
            $query['page'] = (string) $page;
        }

        return '/category/' . $slug . ([] === $query ? '' : '?' . http_build_query($query));
    }
}
