<?php

declare(strict_types=1);

namespace App\Tests\Integration\Blog;

use App\Blog\Application\ReadModel\CategoryRef;
use App\Blog\Application\ReadModel\PostListItem;
use App\Blog\Domain\ValueObject\PostId;
use App\Blog\Domain\ValueObject\Slug;
use App\Blog\Infrastructure\Persistence\Mysql\PdoPostQuery;
use App\Blog\Infrastructure\Persistence\Mysql\PdoSimilarPostsQuery;
use App\Tests\Integration\IntegrationTestCase;

final class PostPageQueryTest extends IntegrationTestCase
{
    private PdoPostQuery $posts;

    private PdoSimilarPostsQuery $similar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->posts = new PdoPostQuery($this->connection);
        $this->similar = new PdoSimilarPostsQuery($this->connection);
    }

    public function testReturnsWholePost(): void
    {
        $category = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Статья', 'statya', '2026-09-01 10:00:00', [$category], 42, 'posts/cover.jpg');

        $post = $this->posts->findBySlug(Slug::fromString('statya'));

        self::assertNotNull($post);
        self::assertSame('Статья', $post->title);
        self::assertSame('statya', $post->slug);
        self::assertSame('Описание Статья', $post->description);
        self::assertSame('Текст Статья', $post->body);
        self::assertSame('/uploads/posts/cover.jpg', $post->imageUrl);
        self::assertSame(42, $post->views);
        self::assertSame('2026-09-01 10:00', $post->publishedAt->format('Y-m-d H:i'));
    }

    public function testListsCategoriesAlphabetically(): void
    {
        $second = $this->fixtures->category('Будни', 'budni');
        $first = $this->fixtures->category('Аналитика', 'analitika');
        $this->fixtures->post('Статья', 'statya', '2026-09-01 10:00:00', [$second, $first]);

        $post = $this->posts->findBySlug(Slug::fromString('statya'));

        self::assertSame(
            ['Аналитика', 'Будни'],
            array_map(static fn (CategoryRef $c): string => $c->title, $post?->categories ?? []),
        );
    }

    public function testUnknownSlugGivesNull(): void
    {
        self::assertNull($this->posts->findBySlug(Slug::fromString('net-takoy')));
    }

    public function testDraftIsNotReachable(): void
    {
        $category = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Черновик', 'draft', null, [$category]);

        self::assertNull($this->posts->findBySlug(Slug::fromString('draft')));
    }

    public function testScheduledPostIsNotReachableYet(): void
    {
        $category = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Отложена', 'scheduled', '2099-01-01 00:00:00', [$category]);

        self::assertNull($this->posts->findBySlug(Slug::fromString('scheduled')));
    }

    public function testMoreSharedCategoriesRankHigher(): void
    {
        $a = $this->fixtures->category('А', 'a');
        $b = $this->fixtures->category('Б', 'b');
        $c = $this->fixtures->category('В', 'v');

        $source = $this->fixtures->post('Исходная', 'source', '2026-09-10 10:00:00', [$a, $b]);
        $this->fixtures->post('Одна общая', 'one', '2026-09-09 10:00:00', [$a, $c]);
        $this->fixtures->post('Две общих', 'two', '2026-09-01 10:00:00', [$a, $b]);

        self::assertSame(
            ['Две общих', 'Одна общая'],
            $this->titles($source, 2),
            'Две общих категории важнее свежести',
        );
    }

    public function testEqualOverlapIsResolvedByRecency(): void
    {
        $a = $this->fixtures->category('А', 'a');

        $source = $this->fixtures->post('Исходная', 'source', '2026-09-10 10:00:00', [$a]);
        $this->fixtures->post('Старая', 'old', '2026-09-01 10:00:00', [$a]);
        $this->fixtures->post('Свежая', 'fresh', '2026-09-09 10:00:00', [$a]);

        self::assertSame(['Свежая', 'Старая'], $this->titles($source, 2));
    }

    public function testSourcePostIsExcluded(): void
    {
        $a = $this->fixtures->category('А', 'a');
        $source = $this->fixtures->post('Исходная', 'source', '2026-09-10 10:00:00', [$a]);
        $this->fixtures->post('Другая', 'other', '2026-09-09 10:00:00', [$a]);

        self::assertSame(['Другая'], $this->titles($source, 3));
    }

    public function testDraftsAndFuturePostsAreNotSuggested(): void
    {
        $a = $this->fixtures->category('А', 'a');
        $source = $this->fixtures->post('Исходная', 'source', '2026-09-10 10:00:00', [$a]);
        $this->fixtures->post('Черновик', 'draft', null, [$a]);
        $this->fixtures->post('Отложена', 'scheduled', '2099-01-01 00:00:00', [$a]);

        self::assertSame([], $this->titles($source, 3));
    }

    public function testBlockIsToppedUpWithRecentPosts(): void
    {
        $a = $this->fixtures->category('А', 'a');
        $b = $this->fixtures->category('Б', 'b');

        $source = $this->fixtures->post('Исходная', 'source', '2026-09-10 10:00:00', [$a]);
        $this->fixtures->post('Похожая', 'similar', '2026-09-01 10:00:00', [$a]);
        $this->fixtures->post('Чужая свежая', 'foreign-fresh', '2026-09-09 10:00:00', [$b]);
        $this->fixtures->post('Чужая старая', 'foreign-old', '2026-08-01 10:00:00', [$b]);

        self::assertSame(
            ['Похожая', 'Чужая свежая', 'Чужая старая'],
            $this->titles($source, 3),
            'Сначала похожая, затем добор свежими из блога',
        );
    }

    public function testTopUpDoesNotDuplicateAlreadyChosen(): void
    {
        $a = $this->fixtures->category('А', 'a');
        $source = $this->fixtures->post('Исходная', 'source', '2026-09-10 10:00:00', [$a]);
        $this->fixtures->post('Похожая', 'similar', '2026-09-09 10:00:00', [$a]);

        $titles = $this->titles($source, 3);

        self::assertSame(['Похожая'], $titles);
        self::assertCount(\count($titles), array_unique($titles));
    }

    public function testNeverReturnsMoreThanRequested(): void
    {
        $a = $this->fixtures->category('А', 'a');
        $source = $this->fixtures->post('Исходная', 'source', '2026-09-20 10:00:00', [$a]);

        foreach (range(1, 8) as $n) {
            $this->fixtures->post("Статья {$n}", "statya-{$n}", sprintf('2026-09-%02d 10:00:00', $n), [$a]);
        }

        self::assertCount(3, $this->titles($source, 3));
    }

    public function testRejectsNonPositiveLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->similar->forPost(PostId::fromInt(1), 0);
    }

    /**
     * @return list<string>
     */
    private function titles(int $postId, int $limit): array
    {
        return array_map(
            static fn (PostListItem $post): string => $post->title,
            $this->similar->forPost(PostId::fromInt($postId), $limit),
        );
    }
}
