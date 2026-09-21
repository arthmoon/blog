<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * Логгер, который запоминает записи вместо файла.
 *
 * @phpstan-type Record array{level: string, message: string, context: array<string, mixed>}
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message . ' ' . json_encode($context, \JSON_UNESCAPED_UNICODE),
            'context' => $context,
        ];
    }
}
