<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Container;

final class CircularDependency extends \RuntimeException
{
    /**
     * @param list<string> $chain
     */
    public function __construct(array $chain, string $repeated)
    {
        parent::__construct(sprintf(
            'Циклическая зависимость: %s -> %s.',
            implode(' -> ', $chain),
            $repeated,
        ));
    }
}
