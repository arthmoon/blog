<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ui\Web\Controller;

use App\Blog\Application\Port\HomePageQuery;
use App\Blog\Application\ReadModel\CategoryRef;
use App\Blog\Application\ReadModel\CategoryWithPosts;
use App\Shared\Infrastructure\Http\Request;
use App\Tests\Support\ArrayTemplateRenderer;
use App\Ui\Web\Controller\HomeController;
use PHPUnit\Framework\TestCase;

final class HomeControllerTest extends TestCase
{
    public function testAsksForThreePostsPerCategory(): void
    {
        $query = $this->query();
        $controller = new HomeController($query, new ArrayTemplateRenderer());

        $controller(new Request('GET', '/'));

        self::assertSame([3], $query->requestedLimits);
    }

    public function testPassesSectionsToTemplate(): void
    {
        $renderer = new ArrayTemplateRenderer();
        $controller = new HomeController($this->query(), $renderer);

        $response = $controller(new Request('GET', '/'));

        self::assertSame(200, $response->status);
        self::assertSame('home.tpl', $renderer->rendered[0]['template']);
        self::assertCount(1, $renderer->rendered[0]['data']['sections']);
    }

    private function query(): HomePageQuery
    {
        return new class () implements HomePageQuery {
            /** @var list<int> */
            public array $requestedLimits = [];

            public function categoriesWithLatestPosts(int $postsPerCategory): array
            {
                $this->requestedLimits[] = $postsPerCategory;

                return [new CategoryWithPosts(new CategoryRef('Новости', 'novosti'), [])];
            }
        };
    }
}
