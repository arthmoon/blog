<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

/**
 * Совпавший маршрут: какой контроллер звать и что извлечено из адреса.
 */
final readonly class Route
{
    /**
     * @param class-string          $handler
     * @param array<string, string> $parameters
     */
    public function __construct(
        public string $handler,
        public array $parameters = [],
    ) {
    }
}
