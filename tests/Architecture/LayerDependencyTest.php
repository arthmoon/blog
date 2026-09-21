<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Без фреймворка правило «зависимости направлены внутрь» ничем не обеспечено,
 * поэтому проверяем его тестом, а не договорённостью.
 *
 * Проверка намеренно простая: разбираются только выражения use и прямые
 * упоминания инфраструктурных классов. Этого достаточно, чтобы поймать
 * случайный импорт не из того слоя, ради чего проверка и заводилась.
 */
final class LayerDependencyTest extends TestCase
{
    /**
     * Каталог внутри src/ => namespace-префиксы, запрещённые в нём.
     */
    private const array FORBIDDEN_IMPORTS = [
        'Blog/Domain' => [
            'App\Blog\Application',
            'App\Blog\Infrastructure',
            'App\Shared',
            'App\Ui',
        ],
        'Blog/Application' => [
            'App\Blog\Infrastructure',
            'App\Ui',
        ],
        'Ui' => [
            'App\Blog\Infrastructure',
        ],
    ];

    /**
     * Слои, которым нечего знать о конкретных технологиях хранения и вывода.
     */
    private const array TECHNOLOGY_FREE_LAYERS = ['Blog/Domain', 'Blog/Application'];

    private const array TECHNOLOGY_MARKERS = ['PDO', 'Smarty', '$_GET', '$_POST', '$_SERVER'];

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function layers(): iterable
    {
        foreach (self::FORBIDDEN_IMPORTS as $layer => $forbidden) {
            yield $layer => [$layer, $forbidden];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function technologyFreeLayers(): iterable
    {
        foreach (self::TECHNOLOGY_FREE_LAYERS as $layer) {
            yield $layer => [$layer];
        }
    }

    /**
     * @param list<string> $forbidden
     */
    #[DataProvider('layers')]
    public function testLayerDoesNotImportOuterLayers(string $layer, array $forbidden): void
    {
        $violations = [];

        foreach ($this->phpFilesIn($layer) as $file) {
            foreach ($this->importsOf($file) as $import) {
                foreach ($forbidden as $prefix) {
                    if (str_starts_with($import, $prefix)) {
                        $violations[] = sprintf('%s импортирует %s', $this->relative($file), $import);
                    }
                }
            }
        }

        self::assertSame([], $violations, sprintf('Нарушено направление зависимостей в слое %s', $layer));
    }

    #[DataProvider('technologyFreeLayers')]
    public function testLayerIsFreeOfInfrastructureDetails(string $layer): void
    {
        $violations = [];

        foreach ($this->phpFilesIn($layer) as $file) {
            $code = (string) file_get_contents($file);

            foreach (self::TECHNOLOGY_MARKERS as $marker) {
                if (str_contains($code, $marker)) {
                    $violations[] = sprintf('%s упоминает %s', $this->relative($file), $marker);
                }
            }
        }

        self::assertSame([], $violations, sprintf('В слое %s протекла инфраструктура', $layer));
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $layer): array
    {
        $path = $this->projectRoot() . '/src/' . $layer;

        if (!is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && 'php' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function importsOf(string $file): array
    {
        preg_match_all(
            '/^use\s+(?:function\s+|const\s+)?([^;\s]+)/m',
            (string) file_get_contents($file),
            $matches,
        );

        return $matches[1];
    }

    private function relative(string $file): string
    {
        return str_replace($this->projectRoot() . '/', '', $file);
    }

    private function projectRoot(): string
    {
        return \dirname(__DIR__, 2);
    }
}
