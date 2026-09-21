<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ui\Web\Controller;

use App\Blog\Application\Command\RegisterPostView;
use App\Blog\Application\Port\PostQuery;
use App\Blog\Application\Port\SimilarPostsQuery;
use App\Blog\Application\ReadModel\PostDetail;
use App\Blog\Domain\ValueObject\PostId;
use App\Blog\Domain\ValueObject\Slug;
use App\Shared\Infrastructure\Bus\DeferredCommandBus;
use App\Shared\Infrastructure\Http\PageNotFound;
use App\Shared\Infrastructure\Http\Request;
use App\Tests\Support\ArrayTemplateRenderer;
use App\Tests\Support\RecordingLogger;
use App\Ui\Web\Controller\PostController;
use PHPUnit\Framework\TestCase;

final class PostControllerTest extends TestCase
{
    private DeferredCommandBus $deferred;

    /** @var list<int> */
    private array $counted = [];

    protected function setUp(): void
    {
        $this->counted = [];
        $this->deferred = new DeferredCommandBus(new RecordingLogger());
        $this->deferred->register(RegisterPostView::class, function (RegisterPostView $command): void {
            $this->counted[] = $command->postId->value;
        });
    }

    public function testQueuesViewInsteadOfCountingItImmediately(): void
    {
        $renderer = new ArrayTemplateRenderer();

        $this->controller($renderer)(new Request('GET', '/posts/statya', attributes: ['slug' => 'statya']));

        self::assertSame([], $this->counted, 'Контроллер не должен считать просмотр сам');
        self::assertSame(1, $this->deferred->pending());

        $this->deferred->flush();

        self::assertSame([42], $this->counted);
    }

    public function testRendersPostWithSimilar(): void
    {
        $renderer = new ArrayTemplateRenderer();

        $response = $this->controller($renderer)(new Request('GET', '/posts/statya', attributes: ['slug' => 'statya']));

        self::assertSame(200, $response->status);
        self::assertSame('post.tpl', $renderer->rendered[0]['template']);
        self::assertArrayHasKey('post', $renderer->rendered[0]['data']);
        self::assertArrayHasKey('similar', $renderer->rendered[0]['data']);
    }

    public function testUnknownSlugIsNotFound(): void
    {
        $this->expectException(PageNotFound::class);

        $this->controller(new ArrayTemplateRenderer(), found: false)(
            new Request('GET', '/posts/net', attributes: ['slug' => 'net']),
        );
    }

    public function testMalformedSlugIsNotFound(): void
    {
        $this->expectException(PageNotFound::class);

        $this->controller(new ArrayTemplateRenderer())(
            new Request('GET', '/posts/ПЛОХОЙ СЛАГ', attributes: ['slug' => 'ПЛОХОЙ СЛАГ']),
        );
    }

    private function controller(ArrayTemplateRenderer $renderer, bool $found = true): PostController
    {
        $posts = new class ($found) implements PostQuery {
            public function __construct(private readonly bool $found)
            {
            }

            public function findBySlug(Slug $slug): ?PostDetail
            {
                return $this->found ? new PostDetail(
                    42,
                    'Статья',
                    $slug->value,
                    'Описание',
                    'Текст',
                    null,
                    7,
                    new \DateTimeImmutable('2026-09-10 10:00:00'),
                    [],
                ) : null;
            }
        };

        $similar = new class () implements SimilarPostsQuery {
            public function forPost(PostId $postId, int $limit): array
            {
                return [];
            }
        };

        return new PostController($posts, $similar, $this->deferred, $renderer);
    }
}
