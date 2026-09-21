<?php

declare(strict_types=1);

namespace App\Blog\Domain\ValueObject;

/**
 * Счётчик просмотров.
 *
 * Объект неизменяемый: increment() возвращает новое значение. В базу счётчик
 * пишется выражением views = views + 1, чтобы не терять параллельные показы,
 * так что это значение — снимок на момент чтения, а не источник правды.
 */
final readonly class ViewCount
{
    private function __construct(public int $value)
    {
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromInt(int $value): self
    {
        if ($value < 0) {
            throw new \InvalidArgumentException(sprintf('Счётчик просмотров не может быть отрицательным, получено %d.', $value));
        }

        return new self($value);
    }

    public function increment(): self
    {
        return new self($this->value + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
