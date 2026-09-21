<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Event;

use App\Blog\Domain\Event\DomainEvent;

/**
 * Диспетчер с картой подписчиков: класс события — список обработчиков.
 *
 * Сопоставление точное, без учёта наследования: события у нас final,
 * иерархии нет, и обход родительских классов только запутал бы.
 */
final class ListenerDomainEventDispatcher implements DomainEventDispatcher
{
    /** @var array<class-string<DomainEvent>, list<callable(DomainEvent): void>> */
    private array $listeners = [];

    /**
     * @param class-string<DomainEvent>       $eventClass
     * @param callable(covariant DomainEvent): void $listener
     */
    public function subscribe(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(array $events): void
    {
        foreach ($events as $event) {
            foreach ($this->listeners[$event::class] ?? [] as $listener) {
                $listener($event);
            }
        }
    }
}
