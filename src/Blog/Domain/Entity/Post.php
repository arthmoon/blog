<?php

declare(strict_types=1);

namespace App\Blog\Domain\Entity;

use App\Blog\Domain\Exception\PostWithoutCategory;
use App\Blog\Domain\ValueObject\CategoryId;
use App\Blog\Domain\ValueObject\ImagePath;
use App\Blog\Domain\ValueObject\PostId;
use App\Blog\Domain\ValueObject\Slug;
use App\Blog\Domain\ValueObject\ViewCount;

/**
 * Статья блога.
 *
 * Статья держит только идентификаторы своих категорий, а не сами объекты:
 * для инварианта «хотя бы одна категория» этого достаточно, а грузить
 * коллекцию категорий ради сохранения статьи незачем.
 *
 * Публикация отделена от создания: create() даёт черновик, publish() задаёт
 * момент публикации. Дата может быть в будущем — отложенная публикация,
 * поэтому видимость статьи считается от текущего времени, а не от того,
 * что published_at просто заполнен.
 */
final class Post
{
    private const int TITLE_MAX_LENGTH = 200;

    private const int DESCRIPTION_MAX_LENGTH = 500;

    /** @var list<CategoryId> */
    private array $categoryIds;

    /**
     * @param list<CategoryId> $categoryIds
     */
    private function __construct(
        private ?PostId $id,
        private readonly string $title,
        private readonly Slug $slug,
        private readonly string $description,
        private readonly string $body,
        private readonly ?ImagePath $image,
        array $categoryIds,
        private ViewCount $views,
        private ?\DateTimeImmutable $publishedAt,
    ) {
        $this->categoryIds = $categoryIds;
    }

    /**
     * Новый черновик.
     *
     * @param list<CategoryId> $categoryIds
     */
    public static function create(
        string $title,
        string $description,
        string $body,
        array $categoryIds,
        ?ImagePath $image = null,
        ?Slug $slug = null,
    ): self {
        $title = trim($title);
        $description = trim($description);
        $body = trim($body);

        self::assertTitle($title);
        self::assertDescription($description);
        self::assertBody($body);

        return new self(
            null,
            $title,
            $slug ?? Slug::fromTitle($title),
            $description,
            $body,
            $image,
            self::normalizeCategories($categoryIds),
            ViewCount::zero(),
            null,
        );
    }

    /**
     * Восстановление из базы. Как и у категории, инварианты не перепроверяются.
     *
     * @param list<CategoryId> $categoryIds
     */
    public static function restore(
        PostId $id,
        string $title,
        Slug $slug,
        string $description,
        string $body,
        ?ImagePath $image,
        array $categoryIds,
        ViewCount $views,
        ?\DateTimeImmutable $publishedAt,
    ): self {
        return new self($id, $title, $slug, $description, $body, $image, $categoryIds, $views, $publishedAt);
    }

    public function assignId(PostId $id): void
    {
        if (null !== $this->id) {
            throw new \LogicException('Идентификатор статьи уже назначен.');
        }

        $this->id = $id;
    }

    public function publish(\DateTimeImmutable $publishedAt): void
    {
        if (null !== $this->publishedAt) {
            throw new \LogicException('Статья уже опубликована.');
        }

        $this->publishedAt = $publishedAt;
    }

    public function unpublish(): void
    {
        if (null === $this->publishedAt) {
            throw new \LogicException('Статья не опубликована.');
        }

        $this->publishedAt = null;
    }

    /**
     * Видима ли статья читателю. Дата публикации в будущем означает,
     * что статья ещё не показывается, хотя published_at уже заполнен.
     */
    public function isPublished(\DateTimeImmutable $now): bool
    {
        return null !== $this->publishedAt && $this->publishedAt <= $now;
    }

    /**
     * @param list<CategoryId> $categoryIds
     */
    public function changeCategories(array $categoryIds): void
    {
        $this->categoryIds = self::normalizeCategories($categoryIds);
    }

    /**
     * Счётчик в базе увеличивается атомарным UPDATE ... views = views + 1,
     * чтобы не терять параллельные показы. Здесь мы лишь держим согласованным
     * снимок, прочитанный в этом запросе.
     */
    public function registerView(): void
    {
        $this->views = $this->views->increment();
    }

    public function id(): ?PostId
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

    public function body(): string
    {
        return $this->body;
    }

    public function image(): ?ImagePath
    {
        return $this->image;
    }

    /**
     * @return list<CategoryId>
     */
    public function categoryIds(): array
    {
        return $this->categoryIds;
    }

    public function views(): ViewCount
    {
        return $this->views;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    /**
     * @param list<CategoryId> $categoryIds
     *
     * @return list<CategoryId>
     */
    private static function normalizeCategories(array $categoryIds): array
    {
        $unique = [];

        foreach ($categoryIds as $categoryId) {
            if (!$categoryId instanceof CategoryId) {
                throw new \InvalidArgumentException('Список категорий должен состоять из объектов CategoryId.');
            }

            $unique[$categoryId->value] = $categoryId;
        }

        if ([] === $unique) {
            throw new PostWithoutCategory();
        }

        return array_values($unique);
    }

    private static function assertTitle(string $title): void
    {
        if ('' === $title) {
            throw new \InvalidArgumentException('Заголовок статьи не может быть пустым.');
        }

        if (mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Заголовок статьи длиннее %d символов.', self::TITLE_MAX_LENGTH));
        }
    }

    private static function assertDescription(string $description): void
    {
        if ('' === $description) {
            throw new \InvalidArgumentException('Описание статьи не может быть пустым: оно выводится в списках.');
        }

        if (mb_strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Описание статьи длиннее %d символов.', self::DESCRIPTION_MAX_LENGTH));
        }
    }

    private static function assertBody(string $body): void
    {
        if ('' === $body) {
            throw new \InvalidArgumentException('Текст статьи не может быть пустым.');
        }
    }
}
