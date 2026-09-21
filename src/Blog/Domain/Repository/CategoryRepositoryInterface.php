<?php

declare(strict_types=1);

namespace App\Blog\Domain\Repository;

use App\Blog\Domain\Entity\Category;

/**
 * Хранилище категорий со стороны записи.
 *
 * Методов поиска здесь нет намеренно: все страницы читают категории через
 * query-сервисы, минуя агрегаты. Добавлять findBySlug() «на будущее» значило
 * бы завести код, который никто не вызывает.
 */
interface CategoryRepositoryInterface
{
    /**
     * Сохраняет категорию: INSERT для новой, UPDATE для существующей.
     * Новой категории проставляет идентификатор, выданный базой.
     */
    public function save(Category $category): void;
}
