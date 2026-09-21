<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Event\DomainEvent;
use App\Blog\Domain\Repository\PostRepositoryInterface;
use App\Blog\Domain\ValueObject\PostId;

/**
 * Хранилище статей в памяти.
 *
 * Повторяет две важные черты настоящего: выдаёт идентификатор при вставке
 * и забирает накопленные события при каждом сохранении — как это делает
 * PDO-реализация внутри транзакции.
 */
final class InMemoryPostRepository implements PostRepositoryInterface
{
    /** @var list<Post> */
    public array $saved = [];

    /** @var list<DomainEvent> */
    public array $dispatchedEvents = [];

    /** @var array<int, int> */
    public array $viewIncrements = [];

    public int $saveCalls = 0;

    private int $nextId = 1;

    public function save(Post $post): void
    {
        ++$this->saveCalls;

        if ($post->isNew()) {
            $post->assignId(PostId::fromInt($this->nextId++));
            $this->saved[] = $post;
        }

        foreach ($post->releaseEvents() as $event) {
            $this->dispatchedEvents[] = $event;
        }
    }

    public function incrementViews(PostId $postId): void
    {
        $this->viewIncrements[$postId->value] = ($this->viewIncrements[$postId->value] ?? 0) + 1;
    }
}
