<?php

declare(strict_types=1);

namespace App\Blog\Application\ReadModel;

/**
 * Страница списка статей вместе с данными для постраничной навигации.
 *
 * Общее количество приходит отдельным COUNT(*): SQL_CALC_FOUND_ROWS
 * объявлен устаревшим в MySQL 8, а два простых запроса оптимизатор
 * отрабатывает лучше одного хитрого.
 */
final readonly class PostsPage
{
    /**
     * @param list<PostListItem> $items
     */
    private function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    /**
     * @param list<PostListItem> $items
     */
    public static function create(array $items, int $total, int $page, int $perPage): self
    {
        if ($page < 1) {
            throw new \InvalidArgumentException(sprintf('Номер страницы должен быть не меньше единицы, получено %d.', $page));
        }

        if ($perPage < 1) {
            throw new \InvalidArgumentException(sprintf('Размер страницы должен быть не меньше единицы, получено %d.', $perPage));
        }

        if ($total < 0) {
            throw new \InvalidArgumentException(sprintf('Общее количество не может быть отрицательным, получено %d.', $total));
        }

        return new self($items, $total, $page, $perPage);
    }

    public static function empty(int $page, int $perPage): self
    {
        return self::create([], 0, $page, $perPage);
    }

    /**
     * Пустой список — это всё равно одна страница: иначе навигация
     * показала бы «страница 1 из 0».
     */
    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pageCount();
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    /**
     * Страница за пределами диапазона. Контроллер отвечает на это 404,
     * чтобы поисковики не индексировали пустые страницы.
     */
    public function isOutOfRange(): bool
    {
        return $this->page > $this->pageCount();
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
