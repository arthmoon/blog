<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Container;

final class ServiceNotFound extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Сервис «%s» не зарегистрирован в контейнере.', $id));
    }
}
