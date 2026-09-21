<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Blog\Application\Port\SeedContent;
use App\Blog\Application\Seed\CategoryDraft;
use App\Blog\Application\Seed\PostDraft;

/**
 * Предсказуемые тексты: заголовки пронумерованы, значит слаги уникальны.
 */
final class StaticSeedContent implements SeedContent
{
    public function categories(int $count): array
    {
        $drafts = [];

        for ($i = 1; $i <= $count; ++$i) {
            $drafts[] = new CategoryDraft(sprintf('Категория %d', $i), sprintf('Описание категории %d', $i));
        }

        return $drafts;
    }

    public function posts(int $count): array
    {
        $drafts = [];

        for ($i = 1; $i <= $count; ++$i) {
            $drafts[] = new PostDraft(
                sprintf('Статья %d', $i),
                sprintf('Описание статьи %d', $i),
                sprintf('Текст статьи %d', $i),
            );
        }

        return $drafts;
    }
}
