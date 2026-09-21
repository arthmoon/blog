<?php

declare(strict_types=1);

/**
 * Скрипт для проверки перехвата фатальных ошибок.
 *
 * Запускается отдельным процессом с маленьким memory_limit: внутри одного
 * процесса исчерпание памяти проверить нельзя — оно его и убивает.
 * Единственный аргумент — путь к файлу лога.
 */

use App\Shared\Infrastructure\Error\FatalErrorHandler;
use App\Shared\Infrastructure\Log\FileLogger;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new FatalErrorHandler(new FileLogger($argv[1])))->register();

// Память выбирается мелкими порциями: к моменту падения свободного
// места почти не остаётся, и без резерва обработчик не успевает
// записать лог. Крупные куски такой картины не дают — освободившийся
// мегабайт сам становится запасом.
$eaten = [];

while (true) {
    $eaten[] = str_repeat('x', 512);
}
