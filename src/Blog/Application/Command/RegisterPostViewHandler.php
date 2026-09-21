<?php

declare(strict_types=1);

namespace App\Blog\Application\Command;

use App\Blog\Domain\Repository\PostRepositoryInterface;

final readonly class RegisterPostViewHandler
{
    public function __construct(private PostRepositoryInterface $posts)
    {
    }

    public function __invoke(RegisterPostView $command): void
    {
        $this->posts->incrementViews($command->postId);
    }
}
