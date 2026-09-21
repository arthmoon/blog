<?php

declare(strict_types=1);

namespace App\Ui\Web\Controller;

use App\Blog\Application\Command\RegisterPostView;
use App\Blog\Application\Port\PostQuery;
use App\Blog\Application\Port\SimilarPostsQuery;
use App\Blog\Domain\ValueObject\PostId;
use App\Shared\Infrastructure\Bus\DeferredCommandBus;
use App\Shared\Infrastructure\Http\PageNotFound;
use App\Shared\Infrastructure\Http\Request;
use App\Shared\Infrastructure\Http\Response;
use App\Shared\Infrastructure\Template\TemplateRenderer;
use App\Ui\Web\SlugParameter;

/**
 * Страница статьи.
 *
 * Счётчик просмотров кладётся в отложенную шину: читателю незачем ждать
 * запись, которая его не касается. Контроллер при этом не знает ни про
 * очередь, ни про то, что выполнение отложено, — он просто отправляет
 * команду.
 */
final readonly class PostController
{
    private const int SIMILAR_LIMIT = 3;

    public function __construct(
        private PostQuery $posts,
        private SimilarPostsQuery $similar,
        private DeferredCommandBus $deferred,
        private TemplateRenderer $templates,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $post = $this->posts->findBySlug(SlugParameter::of($request));

        if (null === $post) {
            throw new PageNotFound();
        }

        $postId = PostId::fromInt($post->id);

        $this->deferred->push(new RegisterPostView($postId));

        return Response::html($this->templates->render('post.tpl', [
            'post' => $post,
            'similar' => $this->similar->forPost($postId, self::SIMILAR_LIMIT),
        ]));
    }
}
