<?php

declare(strict_types=1);

namespace App\Blog\Domain\ValueObject;

/**
 * Идентификатор категории. Как и у статьи, значение выдаёт база.
 */
final readonly class CategoryId
{
    private function __construct(public int $value)
    {
    }

    public static function fromInt(int $value): self
    {
        if ($value < 1) {
            throw new \InvalidArgumentException(sprintf('Идентификатор категории должен быть положительным, получено %d.', $value));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
