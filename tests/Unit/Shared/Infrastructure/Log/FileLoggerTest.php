<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Log;

use App\Shared\Infrastructure\Log\FileLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class FileLoggerTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/blog-log-' . bin2hex(random_bytes(4)) . '/app.log';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        @rmdir(\dirname($this->path));
    }

    public function testCreatesDirectoryAndWritesLine(): void
    {
        (new FileLogger($this->path))->error('всё сломалось');

        self::assertFileExists($this->path);
        self::assertStringContainsString('ERROR: всё сломалось', $this->contents());
    }

    public function testAppendsInsteadOfOverwriting(): void
    {
        $logger = new FileLogger($this->path);
        $logger->warning('первая');
        $logger->warning('вторая');

        self::assertCount(2, array_filter(explode("\n", $this->contents())));
    }

    public function testInterpolatesContext(): void
    {
        (new FileLogger($this->path))->error('Не найдено: {slug}', ['slug' => 'novosti']);

        self::assertStringContainsString('Не найдено: novosti', $this->contents());
    }

    public function testContextIsAlsoStoredAsJson(): void
    {
        (new FileLogger($this->path))->error('Сбой', ['line' => 42]);

        self::assertStringContainsString('{"line":42}', $this->contents());
    }

    public function testRespectsMinimumLevel(): void
    {
        $logger = new FileLogger($this->path, LogLevel::WARNING);
        $logger->debug('не должно попасть');
        $logger->info('тоже не должно');
        $logger->warning('а это должно');
        $logger->critical('и это');

        $contents = $this->contents();

        self::assertStringNotContainsString('не должно попасть', $contents);
        self::assertStringNotContainsString('тоже не должно', $contents);
        self::assertStringContainsString('а это должно', $contents);
        self::assertStringContainsString('и это', $contents);
    }

    public function testUnwritablePathDoesNotThrow(): void
    {
        $logger = new FileLogger('/proc/nonexistent/app.log');

        $logger->error('падение логгера не должно ронять страницу');

        self::assertTrue(true);
    }

    private function contents(): string
    {
        return (string) file_get_contents($this->path);
    }
}
