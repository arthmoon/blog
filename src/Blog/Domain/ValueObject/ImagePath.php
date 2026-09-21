<?php

declare(strict_types=1);

namespace App\Blog\Domain\ValueObject;

/**
 * Путь к изображению статьи относительно каталога public/uploads.
 *
 * Значение приходит извне и подставляется в HTML, поэтому здесь же
 * отсекаются выход за пределы каталога и неподходящие расширения.
 */
final readonly class ImagePath implements \Stringable
{
    private const string PUBLIC_PREFIX = '/uploads/';

    private const array ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** Только относительный путь: без ведущего слеша, пробелов и обратных слешей. */
    private const string PATTERN = '#^[a-z0-9][a-z0-9/_-]*\.[a-z0-9]+$#';

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ('' === $value) {
            throw new \InvalidArgumentException('Путь к изображению не может быть пустым.');
        }

        if (str_contains($value, '..')) {
            throw new \InvalidArgumentException(sprintf('Путь «%s» выходит за пределы каталога загрузок.', $value));
        }

        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(sprintf('Путь «%s» должен быть относительным и без спецсимволов.', $value));
        }

        $extension = strtolower(pathinfo($value, \PATHINFO_EXTENSION));

        if (!\in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(sprintf('Расширение «%s» не поддерживается, ожидается одно из: %s.', $extension, implode(', ', self::ALLOWED_EXTENSIONS)));
        }

        return new self($value);
    }

    /** Адрес для атрибута src. */
    public function url(): string
    {
        return self::PUBLIC_PREFIX . $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
