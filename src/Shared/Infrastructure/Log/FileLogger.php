<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Log;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Запись в файл, по строке на событие.
 *
 * Свой, а не monolog: нужного нам здесь — сорок строк, а PSR-3 как контракт
 * даёт и типизацию портов, и готовый NullLogger для тестов. AbstractLogger
 * берёт на себя восемь методов-обёрток, нам остаётся один log().
 *
 * Файл открывается на каждую запись и закрывается сразу: держать дескриптор
 * между запросами незачем, а флаг FILE_APPEND в связке с блокировкой не даёт
 * строкам параллельных процессов перемешаться.
 */
final class FileLogger extends AbstractLogger
{
    /** Чем меньше число, тем серьёзнее уровень. */
    private const array SEVERITY = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7,
    ];

    public function __construct(
        private readonly string $path,
        private readonly string $minimumLevel = LogLevel::DEBUG,
    ) {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $level = (string) $level;

        if (!$this->isLoggable($level)) {
            return;
        }

        $line = sprintf(
            '[%s] %s: %s%s%s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            self::interpolate((string) $message, $context),
            [] === $context ? '' : ' ' . self::encode($context),
            \PHP_EOL,
        );

        $this->ensureDirectory();

        // Ошибку записи глотаем намеренно: падение логгера не должно
        // превращаться в падение страницы.
        @file_put_contents($this->path, $line, \FILE_APPEND | \LOCK_EX);
    }

    private function isLoggable(string $level): bool
    {
        $threshold = self::SEVERITY[$this->minimumLevel] ?? 7;

        return (self::SEVERITY[$level] ?? 7) <= $threshold;
    }

    /**
     * Подстановка {placeholder} из контекста — часть договорённостей PSR-3.
     *
     * @param array<string, mixed> $context
     */
    private static function interpolate(string $message, array $context): string
    {
        $replacements = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || null === $value || $value instanceof \Stringable) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replacements);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function encode(array $context): string
    {
        $encoded = json_encode($context, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR);

        return false === $encoded ? '{}' : $encoded;
    }

    private function ensureDirectory(): void
    {
        $directory = \dirname($this->path);

        if (!is_dir($directory)) {
            @mkdir($directory, 0o775, true);
        }
    }
}
