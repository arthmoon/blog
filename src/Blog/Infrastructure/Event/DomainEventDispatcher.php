<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Event;

use App\Blog\Domain\Event\DomainEvent;

/**
 * Доставка доменных событий подписчикам.
 *
 * Диспетчер вызывается репозиторием внутри транзакции, поэтому подписчик
 * обязан быть транзакционным: он пишет в ту же базу и падает вместе с
 * основной записью. Для нетранзакционной работы — метрик, писем, прогрева
 * внешнего кэша — есть отложенная шина команд, она выполняется после
 * отправки ответа.
 */
interface DomainEventDispatcher
{
    /**
     * @param list<DomainEvent> $events
     */
    public function dispatch(array $events): void;
}
