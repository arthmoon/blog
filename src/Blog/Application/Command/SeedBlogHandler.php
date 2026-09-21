<?php

declare(strict_types=1);

namespace App\Blog\Application\Command;

use App\Blog\Application\Port\CoverImageFactory;
use App\Blog\Application\Port\SeedContent;
use App\Blog\Domain\Entity\Category;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\CategoryRepositoryInterface;
use App\Blog\Domain\Repository\PostRepositoryInterface;
use App\Blog\Domain\ValueObject\CategoryId;

/**
 * Наполнение блога идёт через домен и репозитории, а не пакетным INSERT.
 *
 * Так проверяется, что сторона записи вообще работает, и — главное —
 * заполняется проекция: она перестраивается по событию PostPublished,
 * которого при прямой вставке в таблицы просто не было бы.
 *
 * Очистка прежних данных сюда не входит: это забота консольной команды,
 * иначе в слое приложения появился бы метод «удалить всё», нужный
 * ровно одному инструменту разработчика.
 */
final readonly class SeedBlogHandler
{
    /** Шаг между датами публикации соседних статей. */
    private const int HOURS_BETWEEN_POSTS = 6;

    public function __construct(
        private CategoryRepositoryInterface $categories,
        private PostRepositoryInterface $posts,
        private SeedContent $content,
        private CoverImageFactory $covers,
    ) {
    }

    public function __invoke(SeedBlog $command): SeedBlogResult
    {
        $categoryIds = $this->createCategories($command->categoryCount);
        $created = 0;

        foreach ($this->content->posts($command->postCount) as $index => $draft) {
            $post = Post::create(
                $draft->title,
                $draft->description,
                $draft->body,
                $this->categoriesFor($index, $categoryIds),
                $this->covers->create($draft->title),
            );

            // Домен умеет только «+1», и это правильно: метод «проставь
            // счётчик» в бою не нужен никому. Для сидера цикл в памяти
            // дешевле, чем дырка в модели.
            for ($view = 0; $view < $this->viewsFor($index); ++$view) {
                $post->registerView();
            }

            // Первое сохранение — INSERT, статья получает идентификатор.
            $this->posts->save($post);

            // Публиковать можно только сохранённую статью: событие обязано
            // нести идентификатор. Второе сохранение пишет дату и запускает
            // перестройку проекции.
            $post->publish($this->publicationDate($command->latestPublishedAt, $index));
            $this->posts->save($post);

            ++$created;
        }

        return new SeedBlogResult(\count($categoryIds), $created);
    }

    /**
     * @return list<CategoryId>
     */
    private function createCategories(int $count): array
    {
        $ids = [];

        foreach ($this->content->categories($count) as $draft) {
            $category = Category::create($draft->title, $draft->description);
            $this->categories->save($category);

            $id = $category->id();

            if (null === $id) {
                throw new \LogicException('Репозиторий не проставил идентификатор категории.');
            }

            $ids[] = $id;
        }

        if ([] === $ids) {
            throw new \LogicException('Поставщик данных не вернул ни одной категории.');
        }

        return $ids;
    }

    /**
     * Раскладка детерминированная, а не случайная: иначе тесты главной
     * страницы и похожих статей плавали бы от запуска к запуску.
     *
     * @param list<CategoryId> $categoryIds
     *
     * @return list<CategoryId>
     */
    private function categoriesFor(int $index, array $categoryIds): array
    {
        $primary = $categoryIds[$index % \count($categoryIds)];

        if (0 !== $index % 3) {
            return [$primary];
        }

        // Каждая третья статья попадает в две категории — иначе блок
        // похожих статей нечем было бы наполнить.
        $secondary = $categoryIds[($index + 1) % \count($categoryIds)];

        return $primary->equals($secondary) ? [$primary] : [$primary, $secondary];
    }

    private function publicationDate(\DateTimeImmutable $latest, int $index): \DateTimeImmutable
    {
        return $latest->sub(new \DateInterval(sprintf('PT%dH', $index * self::HOURS_BETWEEN_POSTS)));
    }

    /**
     * Просмотры нужны разные, иначе сортировку по популярности
     * нечем будет показать.
     */
    private function viewsFor(int $index): int
    {
        return ($index * 37) % 500;
    }
}
