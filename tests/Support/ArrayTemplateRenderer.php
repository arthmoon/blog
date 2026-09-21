<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Infrastructure\Template\TemplateRenderer;

/**
 * Рендерер, который вместо HTML отдаёт имя шаблона и переданные данные.
 *
 * Тесту ядра важно, какой шаблон выбран и что в него ушло, а не как
 * выглядит вёрстка. Заодно тесты не зависят от Smarty.
 */
final class ArrayTemplateRenderer implements TemplateRenderer
{
    /** @var list<array{template: string, data: array<string, mixed>}> */
    public array $rendered = [];

    public function render(string $template, array $data = []): string
    {
        $this->rendered[] = ['template' => $template, 'data' => $data];

        return $template . ' ' . json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
