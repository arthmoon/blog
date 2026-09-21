<?php

declare(strict_types=1);

namespace App\Tests\Integration\Web;

use App\Tests\Integration\WebTestCase;

final class PostPageTest extends WebTestCase
{
    public function testShowsWholePost(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Как мы ускорили сборку', 'kak-my-uskorili', '2026-09-15 10:00:00', [$id], 21, 'posts/cover.png');

        $body = $this->get('/posts/kak-my-uskorili')->body;

        self::assertStringContainsString('Как мы ускорили сборку', $body);
        self::assertStringContainsString('Текст Как мы ускорили сборку', $body);
        self::assertStringContainsString('15 сентября 2026', $body);
        self::assertStringContainsString('21 просмотр', $body);
        self::assertStringContainsString('/uploads/posts/cover.png', $body);
        self::assertStringContainsString('href="/category/novosti"', $body);
    }

    public function testUnknownSlugGives404(): void
    {
        self::assertSame(404, $this->get('/posts/net-takoy')->status);
    }

    public function testDraftIsNotReachableByDirectLink(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Черновик', 'draft', null, [$id]);

        self::assertSame(404, $this->get('/posts/draft')->status);
    }

    public function testScheduledPostIsNotReachableYet(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Отложена', 'scheduled', '2099-01-01 00:00:00', [$id]);

        self::assertSame(404, $this->get('/posts/scheduled')->status);
    }

    public function testShowsSimilarPosts(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Исходная', 'source', '2026-09-10 10:00:00', [$id]);
        $this->fixtures->post('Похожая первая', 'similar-1', '2026-09-09 10:00:00', [$id]);
        $this->fixtures->post('Похожая вторая', 'similar-2', '2026-09-08 10:00:00', [$id]);

        $body = $this->get('/posts/source')->body;

        self::assertStringContainsString('Похожие статьи', $body);
        self::assertStringContainsString('Похожая первая', $body);
        self::assertStringContainsString('Похожая вторая', $body);
    }

    public function testSimilarBlockIsHiddenWhenThereIsNothingToShow(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');
        $this->fixtures->post('Единственная', 'only', '2026-09-10 10:00:00', [$id]);

        self::assertStringNotContainsString('Похожие статьи', $this->get('/posts/only')->body);
    }

    public function testBodyIsSplitIntoParagraphs(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');
        $postId = $this->fixtures->post('Статья', 'statya', '2026-09-10 10:00:00', [$id]);

        $this->connection
            ->prepare('UPDATE posts SET body = :body WHERE id = :id')
            ->execute(['body' => "Первый абзац.\n\nВторой абзац.\n\nТретий абзац.", 'id' => $postId]);

        $body = $this->get('/posts/statya')->body;

        self::assertStringContainsString('<p>Первый абзац.</p>', $body);
        self::assertStringContainsString('<p>Второй абзац.</p>', $body);
        self::assertStringContainsString('<p>Третий абзац.</p>', $body);
    }

    public function testBodyIsEscapedParagraphByParagraph(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');
        $postId = $this->fixtures->post('Статья', 'statya', '2026-09-10 10:00:00', [$id]);

        $this->connection
            ->prepare('UPDATE posts SET body = :body WHERE id = :id')
            ->execute(['body' => "Обычный абзац.\n\n<script>alert(1)</script>", 'id' => $postId]);

        $body = $this->get('/posts/statya')->body;

        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringContainsString('&lt;script&gt;', $body);
    }

    /**
     * Ради этого и заводилась отложенная шина: счётчик обновляется
     * после того, как ответ ушёл читателю.
     */
    public function testViewIsCountedOnlyAfterTerminate(): void
    {
        $id = $this->fixtures->category('Новости', 'novosti');
        $postId = $this->fixtures->post('Статья', 'statya', '2026-09-10 10:00:00', [$id]);

        self::assertSame(0, $this->views($postId));

        $this->get('/posts/statya');

        self::assertSame(0, $this->views($postId), 'До terminate счётчик трогать нельзя');

        $this->terminate();

        self::assertSame(1, $this->views($postId));
    }

    public function testMissingPostDoesNotCountAView(): void
    {
        $this->get('/posts/net-takoy');
        $this->terminate();

        self::assertSame(
            0,
            (int) $this->connection->query('SELECT COALESCE(SUM(views), 0) FROM posts')->fetchColumn(),
        );
    }

    private function views(int $postId): int
    {
        $statement = $this->connection->prepare('SELECT views FROM posts WHERE id = :id');
        $statement->execute(['id' => $postId]);

        return (int) $statement->fetchColumn();
    }
}
