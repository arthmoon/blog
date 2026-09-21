<?php

declare(strict_types=1);

namespace App\Blog\Application\Command;

/**
 * Создать статью.
 *
 * Категории задаются слагами, а не идентификаторами: команду отдаёт
 * человек из консоли, и слаг он видит в адресе, а число из базы — нет.
 */
final readonly class CreatePost
{
    /**
     * @param list<string>            $categorySlugs
     * @param \DateTimeImmutable|null $publishedAt   null — сохранить черновиком
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $body,
        public array $categorySlugs,
        public ?\DateTimeImmutable $publishedAt,
    ) {
        if ([] === $categorySlugs) {
            throw new \InvalidArgumentException('Нужна хотя бы одна категория.');
        }
    }
}
