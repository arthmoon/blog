<?php

declare(strict_types=1);

namespace App\Blog\Domain\ValueObject;

/**
 * Идентификатор статьи.
 *
 * Значение выдаёт база (AUTO_INCREMENT), поэтому у несохранённой статьи
 * идентификатора нет: Post::id() вернёт null до первого save().
 */
final readonly class PostId
{
    private function __construct(public int $value)
    {
    }

    public static function fromInt(int $value): self
    {
        if ($value < 1) {
            throw new \InvalidArgumentException(sprintf('Идентификатор статьи должен быть положительным, получено %d.', $value));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
