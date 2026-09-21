<?php

declare(strict_types=1);

namespace App\Blog\Application\Command;

use App\Blog\Application\Port\CategoryQuery;
use App\Blog\Application\Port\CoverImageFactory;
use App\Blog\Domain\Entity\Post;
use App\Blog\Domain\Repository\PostRepositoryInterface;
use App\Blog\Domain\ValueObject\CategoryId;
use App\Blog\Domain\ValueObject\Slug;

/**
 * Создание статьи из консоли.
 *
 * Тот же путь, что у сидера: сохранить, затем опубликовать и сохранить
 * снова. Второе сохранение и порождает PostPublished, по которому
 * проектор перестраивает ленты категорий.
 *
 * Благодаря этому механизм событий работает не только при наполнении
 * базы: статью можно добавить в уже живой блог и увидеть, как она
 * появляется на главной.
 */
final readonly class CreatePostHandler
{
    public function __construct(
        private CategoryQuery $categories,
        private PostRepositoryInterface $posts,
        private CoverImageFactory $covers,
    ) {
    }

    public function __invoke(CreatePost $command): CreatePostResult
    {
        $post = Post::create(
            $command->title,
            $command->description,
            $command->body,
            $this->resolveCategories($command->categorySlugs),
            $this->covers->create($command->title),
        );

        // INSERT: статья получает идентификатор, без него публиковать нельзя.
        $this->posts->save($post);

        if (null !== $command->publishedAt) {
            $post->publish($command->publishedAt);

            // UPDATE вместе с событием и перестройкой проекции.
            $this->posts->save($post);
        }

        $id = $post->id();

        if (null === $id) {
            throw new \LogicException('Репозиторий не проставил идентификатор статьи.');
        }

        return new CreatePostResult(
            $id->value,
            $post->slug()->value,
            null !== $command->publishedAt,
            $command->categorySlugs,
        );
    }

    /**
     * @param list<string> $slugs
     *
     * @return list<CategoryId>
     */
    private function resolveCategories(array $slugs): array
    {
        $ids = [];

        foreach ($slugs as $slug) {
            try {
                $parsed = Slug::fromString($slug);
            } catch (\InvalidArgumentException $e) {
                throw new \RuntimeException(sprintf('«%s» не похоже на слаг категории: %s', $slug, $e->getMessage()));
            }

            $category = $this->categories->findBySlug($parsed);

            if (null === $category) {
                throw new \RuntimeException(sprintf('Категории со слагом «%s» нет.', $slug));
            }

            $ids[] = CategoryId::fromInt($category->id);
        }

        return $ids;
    }
}
