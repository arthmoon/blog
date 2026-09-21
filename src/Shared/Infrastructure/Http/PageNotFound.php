<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

/**
 * Запрошенной страницы нет.
 *
 * Контроллеру достаточно бросить это исключение, а ядро превратит его
 * в 404 с нужным оформлением. Иначе шаблон страницы «не найдено» пришлось
 * бы собирать в каждом контроллере заново.
 */
final class PageNotFound extends \RuntimeException
{
}
