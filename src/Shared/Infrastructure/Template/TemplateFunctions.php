<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Template;

/**
 * Помощники для шаблонов: даты и склонения по-русски.
 *
 * Обычный объект, который кладётся в шаблон под именем fmt, а не плагины
 * Smarty. Так их видно в автодополнении, можно покрыть обычным тестом
 * и не нужно помнить, где регистрируется модификатор.
 */
final readonly class TemplateFunctions
{
    /** @var array<int, string> */
    private const array MONTHS = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];

    /** «15 сентября 2026» */
    public function date(\DateTimeInterface $date): string
    {
        return sprintf(
            '%d %s %d',
            (int) $date->format('j'),
            self::MONTHS[(int) $date->format('n')],
            (int) $date->format('Y'),
        );
    }

    /** Для атрибута datetime у тега time. */
    public function isoDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    /**
     * Русское склонение: 1 просмотр, 2 просмотра, 5 просмотров.
     *
     * Правило то же, что в CLDR: числа 11–14 всегда идут по последней форме,
     * иначе решает последняя цифра.
     */
    public function plural(int $number, string $one, string $few, string $many): string
    {
        $lastTwo = abs($number) % 100;
        $last = $lastTwo % 10;

        $form = match (true) {
            $lastTwo >= 11 && $lastTwo <= 14 => $many,
            1 === $last => $one,
            $last >= 2 && $last <= 4 => $few,
            default => $many,
        };

        return $number . ' ' . $form;
    }

    /**
     * Текст статьи, разбитый на абзацы.
     *
     * Разбивать в шаблоне через nl2br нельзя: модификаторы выполняются
     * до авто-экранирования, и вставленные теги <br> были бы экранированы
     * вместе с текстом. Отдаём массив, шаблон оборачивает каждый абзац
     * в <p> — и каждый экранируется сам по себе.
     *
     * @return list<string>
     */
    public function paragraphs(string $text): array
    {
        $parts = preg_split('/\R{2,}/u', trim($text)) ?: [];

        return array_values(array_filter(
            array_map(trim(...), $parts),
            static fn (string $paragraph): bool => '' !== $paragraph,
        ));
    }

    public function views(int $number): string
    {
        return $this->plural($number, 'просмотр', 'просмотра', 'просмотров');
    }

    public function posts(int $number): string
    {
        return $this->plural($number, 'статья', 'статьи', 'статей');
    }
}
