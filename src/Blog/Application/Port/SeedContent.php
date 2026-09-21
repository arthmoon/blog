<?php

declare(strict_types=1);

namespace App\Blog\Application\Port;

use App\Blog\Application\Seed\CategoryDraft;
use App\Blog\Application\Seed\PostDraft;

/**
 * Поставщик текстов для наполнения блога.
 *
 * Вынесен в порт, чтобы обработчик сидинга не знал, откуда берутся тексты:
 * в тесте это два элемента списка, в бою — генератор. Заодно обработчик
 * остаётся детерминированным и проверяемым.
 */
interface SeedContent
{
    /**
     * Заголовки обязаны быть уникальными: из них строится слаг,
     * а он уникален в базе.
     *
     * @return list<CategoryDraft>
     */
    public function categories(int $count): array;

    /**
     * @return list<PostDraft>
     */
    public function posts(int $count): array;
}
