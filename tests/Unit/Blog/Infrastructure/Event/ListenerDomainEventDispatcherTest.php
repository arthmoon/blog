<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Infrastructure\Event;

use App\Blog\Domain\Event\PostCategoriesChanged;
use App\Blog\Domain\Event\PostPublished;
use App\Blog\Domain\Event\PostUnpublished;
use App\Blog\Domain\ValueObject\CategoryId;
use App\Blog\Domain\ValueObject\PostId;
use App\Blog\Infrastructure\Event\ListenerDomainEventDispatcher;
use PHPUnit\Framework\TestCase;

final class ListenerDomainEventDispatcherTest extends TestCase
{
    public function testDeliversEventToItsListener(): void
    {
        $received = [];
        $dispatcher = new ListenerDomainEventDispatcher();
        $dispatcher->subscribe(PostPublished::class, static function (PostPublished $event) use (&$received): void {
            $received[] = $event->postId->value;
        });

        $dispatcher->dispatch([$this->published(1), $this->published(2)]);

        self::assertSame([1, 2], $received);
    }

    public function testEventWithoutListenersIsIgnored(): void
    {
        $dispatcher = new ListenerDomainEventDispatcher();

        $dispatcher->dispatch([$this->published(1)]);

        self::assertTrue(true, 'Отсутствие подписчика не должно быть ошибкой');
    }

    public function testListenerReceivesOnlyItsOwnEventType(): void
    {
        $published = 0;
        $unpublished = 0;

        $dispatcher = new ListenerDomainEventDispatcher();
        $dispatcher->subscribe(PostPublished::class, static function () use (&$published): void {
            ++$published;
        });
        $dispatcher->subscribe(PostUnpublished::class, static function () use (&$unpublished): void {
            ++$unpublished;
        });

        $dispatcher->dispatch([
            $this->published(1),
            new PostUnpublished(PostId::fromInt(2), [CategoryId::fromInt(1)]),
            new PostCategoriesChanged(PostId::fromInt(3), [CategoryId::fromInt(1)], [CategoryId::fromInt(2)]),
        ]);

        self::assertSame(1, $published);
        self::assertSame(1, $unpublished);
    }

    public function testSeveralListenersRunInSubscriptionOrder(): void
    {
        $order = [];
        $dispatcher = new ListenerDomainEventDispatcher();
        $dispatcher->subscribe(PostPublished::class, static function () use (&$order): void {
            $order[] = 'первый';
        });
        $dispatcher->subscribe(PostPublished::class, static function () use (&$order): void {
            $order[] = 'второй';
        });

        $dispatcher->dispatch([$this->published(1)]);

        self::assertSame(['первый', 'второй'], $order);
    }

    public function testListenerFailurePropagates(): void
    {
        $dispatcher = new ListenerDomainEventDispatcher();
        $dispatcher->subscribe(PostPublished::class, static function (): void {
            throw new \RuntimeException('проекция не обновилась');
        });

        $this->expectException(\RuntimeException::class);

        $dispatcher->dispatch([$this->published(1)]);
    }

    private function published(int $postId): PostPublished
    {
        return new PostPublished(PostId::fromInt($postId), [CategoryId::fromInt(1)]);
    }
}
