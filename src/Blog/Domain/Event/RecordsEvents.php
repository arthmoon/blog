<?php

declare(strict_types=1);

namespace App\Blog\Domain\Event;

/**
 * Накопление событий агрегатом.
 *
 * Трейт, а не базовый класс: наследование в домене ради одного списка
 * обошлось бы дороже, чем стоит.
 */
trait RecordsEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /**
     * Отдаёт накопленные события и очищает список.
     *
     * Забирает их репозиторий внутри транзакции, сразу после записи, но до
     * коммита: проекция обязана меняться вместе с данными, а не после них.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
