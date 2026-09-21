<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Application\Command;

use App\Blog\Application\Command\RegisterPostView;
use App\Blog\Application\Command\RegisterPostViewHandler;
use App\Blog\Domain\ValueObject\PostId;
use App\Tests\Support\InMemoryPostRepository;
use PHPUnit\Framework\TestCase;

final class RegisterPostViewHandlerTest extends TestCase
{
    public function testDelegatesToAtomicIncrement(): void
    {
        $posts = new InMemoryPostRepository();
        $handler = new RegisterPostViewHandler($posts);

        $handler(new RegisterPostView(PostId::fromInt(42)));
        $handler(new RegisterPostView(PostId::fromInt(42)));
        $handler(new RegisterPostView(PostId::fromInt(7)));

        self::assertSame([42 => 2, 7 => 1], $posts->viewIncrements);
        self::assertSame(0, $posts->saveCalls, 'Счётчик не должен идти через сохранение агрегата');
    }
}
