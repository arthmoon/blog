<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Template;

/**
 * Отрисовка шаблона в строку.
 *
 * Именно в строку, а не в вывод: контроллер возвращает Response, и решение
 * об отправке принимается позже. Заодно шаблон можно проверить тестом.
 */
interface TemplateRenderer
{
    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string;
}
