<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Error;

use App\Shared\Infrastructure\Error\FatalErrorHandler;
use App\Tests\Support\RecordingLogger;
use PHPUnit\Framework\TestCase;

final class FatalErrorHandlerTest extends TestCase
{
    private string $log;

    protected function setUp(): void
    {
        $this->log = sys_get_temp_dir() . '/blog-fatal-' . bin2hex(random_bytes(4)) . '.log';
    }

    protected function tearDown(): void
    {
        if (is_file($this->log)) {
            unlink($this->log);
        }
    }

    /**
     * Главная проверка: исчерпание памяти попадает в лог.
     *
     * Отдельным процессом — внутри текущего это невозможно, OOM его убивает.
     *
     * Скрипт выбирает память мелкими порциями. Это существенно: если падать
     * на большом куске, освободившийся мегабайт сам сработает как запас,
     * и проверка пройдёт даже со сломанным резервом. На мелких порциях без
     * резерва лог остаётся пустым — проверено.
     */
    public function testMemoryExhaustionIsLogged(): void
    {
        $script = \dirname(__DIR__, 4) . '/Fixture/exhaust_memory.php';

        self::assertFileExists($script);

        exec(
            sprintf(
                '%s -d memory_limit=16M %s %s 2>&1',
                escapeshellarg(\PHP_BINARY),
                escapeshellarg($script),
                escapeshellarg($this->log),
            ),
            $output,
            $exitCode,
        );

        self::assertNotSame(0, $exitCode, 'Процесс должен упасть, иначе проверять нечего');
        self::assertFileExists($this->log, 'Обработчик обязан успеть записать лог');

        $contents = (string) file_get_contents($this->log);

        self::assertStringContainsString('CRITICAL', $contents);
        self::assertStringContainsString('Allowed memory size', $contents);
    }

    public function testOrdinaryShutdownWritesNothing(): void
    {
        $logger = new RecordingLogger();
        $handler = new FatalErrorHandler($logger);

        // Штатное завершение: последней ошибки нет либо она не фатальная.
        $handler->onShutdown();

        $fatal = array_filter(
            $logger->records,
            static fn (array $record): bool => 'critical' === $record['level'],
        );

        self::assertSame([], $fatal);
    }
}
