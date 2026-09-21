<?php

declare(strict_types=1);

namespace App\Blog\Domain\Entity;

use App\Blog\Domain\ValueObject\CategoryId;
use App\Blog\Domain\ValueObject\Slug;

/**
 * Категория блога.
 *
 * Категория не знает о своих статьях: связь многие-ко-многим живёт на стороне
 * статьи, а списки для страниц собирают query-сервисы. Иначе пришлось бы
 * грузить коллекцию статей ради вывода названия.
 */
final class Category
{
    private const int TITLE_MAX_LENGTH = 150;

    private const int DESCRIPTION_MAX_LENGTH = 1000;

    private function __construct(
        private ?CategoryId $id,
        private readonly string $title,
        private readonly Slug $slug,
        private readonly string $description,
    ) {
    }

    /**
     * Новая категория. Слаг по умолчанию строится из названия,
     * но его можно задать явно — например, когда его выбрал редактор.
     */
    public static function create(string $title, string $description = '', ?Slug $slug = null): self
    {
        $title = trim($title);
        $description = trim($description);

        self::assertTitle($title);
        self::assertDescription($description);

        return new self(null, $title, $slug ?? Slug::fromTitle($title), $description);
    }

    /**
     * Восстановление из базы.
     *
     * Вызывается только репозиторием. Инварианты здесь намеренно не
     * перепроверяются: строка уже прошла проверку при создании, а падать
     * на чтении из-за исторических данных хуже, чем показать их как есть.
     */
    public static function restore(CategoryId $id, string $title, Slug $slug, string $description): self
    {
        return new self($id, $title, $slug, $description);
    }

    /**
     * Идентификатор назначает база, поэтому репозиторий проставляет его
     * сразу после INSERT. Повторный вызов — ошибка в коде, а не в данных.
     */
    public function assignId(CategoryId $id): void
    {
        if (null !== $this->id) {
            throw new \LogicException('Идентификатор категории уже назначен.');
        }

        $this->id = $id;
    }

    public function id(): ?CategoryId
    {
        return $this->id;
    }

    public function isNew(): bool
    {
        return null === $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): Slug
    {
        return $this->slug;
    }

    public function description(): string
    {
        return $this->description;
    }

    private static function assertTitle(string $title): void
    {
        if ('' === $title) {
            throw new \InvalidArgumentException('Название категории не может быть пустым.');
        }

        if (mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Название категории длиннее %d символов.', self::TITLE_MAX_LENGTH));
        }
    }

    private static function assertDescription(string $description): void
    {
        if (mb_strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Описание категории длиннее %d символов.', self::DESCRIPTION_MAX_LENGTH));
        }
    }
}
