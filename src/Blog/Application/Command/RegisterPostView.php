<?php

declare(strict_types=1);

namespace App\Blog\Application\Command;

use App\Blog\Domain\ValueObject\PostId;

/**
 * Засчитать просмотр статьи.
 *
 * Отдельный объект, а не вызов репозитория из контроллера, потому что эта
 * команда кладётся в отложенную очередь и выполняется после отправки ответа.
 * Контроллеру при этом не нужно знать ни про очередь, ни про то, что
 * выполнение отложено.
 */
final readonly class RegisterPostView
{
    public function __construct(public PostId $postId)
    {
    }
}
