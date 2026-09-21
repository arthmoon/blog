<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Seeder;

use App\Blog\Application\Port\SeedContent;
use App\Blog\Application\Seed\CategoryDraft;
use App\Blog\Application\Seed\PostDraft;

/**
 * Тексты для наполнения блога.
 *
 * Генератор детерминированный: никакого mt_rand, всё выводится из номера
 * записи. Повторный запуск даёт ту же базу, а значит интеграционные тесты
 * и скриншоты воспроизводимы. Случайность здесь ничего не добавила бы,
 * кроме плавающих тестов.
 */
final readonly class RussianSeedContent implements SeedContent
{
    /** @var list<array{string, string}> */
    private const array CATEGORIES = [
        ['Архитектура', 'Как устроены наши сервисы и почему именно так'],
        ['Базы данных', 'Схемы, запросы, индексы и всё, что вокруг них'],
        ['Производительность', 'Измеряем, профилируем и ускоряем'],
        ['Тестирование', 'Что и как мы проверяем перед выкатом'],
        ['Инфраструктура', 'Контейнеры, окружения и доставка кода'],
        ['Фронтенд', 'Интерфейсы, вёрстка и то, что видит пользователь'],
        ['Безопасность', 'Уязвимости, доступы и разбор инцидентов'],
        ['Инструменты', 'Чем пользуемся каждый день'],
        ['Команда', 'Процессы, ревью и совместная работа'],
        ['Разное', 'Заметки, которые не поместились в остальные разделы'],
    ];

    /** @var list<string> */
    private const array OPENINGS = [
        'Как мы ускорили',
        'Почему мы отказались от',
        'Что мы поняли про',
        'Разбираем',
        'Пять ошибок в',
        'Чек-лист по',
        'Переписываем',
        'Измеряем',
    ];

    /** @var list<string> */
    private const array SUBJECTS = [
        'сборку проекта',
        'выкатку в продакшен',
        'работу с индексами',
        'кэширование ответов',
        'интеграционные тесты',
        'миграции базы',
        'логирование ошибок',
        'код-ревью',
        'мониторинг очередей',
        'обработку изображений',
    ];

    /** @var list<string> */
    private const array SENTENCES = [
        'Сначала мы измерили, сколько времени уходит на каждый шаг, и только потом трогали код.',
        'Оказалось, что узкое место было совсем не там, где его ожидали увидеть.',
        'Решение выглядело очевидным ровно до того момента, как мы посмотрели на план запроса.',
        'Пришлось выбирать между простотой и скоростью, и мы сознательно взяли простоту.',
        'Отдельно разобрали, что происходит при параллельных запросах к одной строке.',
        'Итоговая схема получилась на два слоя тоньше первоначальной задумки.',
        'Проверку вынесли в отдельный тест, чтобы регрессия не проехала незамеченной.',
        'Цифры до и после приводим ниже, замеры делались на одинаковых данных.',
        'Этот подход не универсален: на другом объёме данных он проиграет.',
        'Главный вывод оказался скучным — читайте план выполнения до того, как оптимизировать.',
        'Мы оставили комментарий прямо в коде, чтобы через полгода не переоткрывать этот вопрос заново.',
        'Коллеги из соседней команды предложили вариант проще, и мы его взяли.',
    ];

    private const int SENTENCES_PER_PARAGRAPH = 3;

    private const int PARAGRAPHS_PER_POST = 4;

    public function categories(int $count): array
    {
        $drafts = [];

        for ($index = 0; $index < $count; ++$index) {
            [$title, $description] = self::CATEGORIES[$index % \count(self::CATEGORIES)];

            // Если категорий запросили больше, чем заготовлено, названия
            // нумеруются: слаг обязан остаться уникальным.
            $suffix = intdiv($index, \count(self::CATEGORIES));

            $drafts[] = new CategoryDraft(
                $suffix > 0 ? sprintf('%s %d', $title, $suffix + 1) : $title,
                $description,
            );
        }

        return $drafts;
    }

    public function posts(int $count): array
    {
        $drafts = [];

        for ($index = 0; $index < $count; ++$index) {
            $title = $this->title($index);
            $body = $this->body($index);

            $drafts[] = new PostDraft($title, $this->description($index), $body);
        }

        return $drafts;
    }

    private function title(int $index): string
    {
        $openings = \count(self::OPENINGS);
        $subjects = \count(self::SUBJECTS);

        $title = sprintf(
            '%s %s',
            self::OPENINGS[$index % $openings],
            self::SUBJECTS[intdiv($index, $openings) % $subjects],
        );

        // Комбинаций хватает на 80 статей; дальше добавляем номер части,
        // иначе совпадут слаги.
        $round = intdiv($index, $openings * $subjects);

        return $round > 0 ? sprintf('%s, часть %d', $title, $round + 1) : $title;
    }

    private function description(int $index): string
    {
        return self::SENTENCES[$index % \count(self::SENTENCES)];
    }

    private function body(int $index): string
    {
        $paragraphs = [];

        for ($paragraph = 0; $paragraph < self::PARAGRAPHS_PER_POST; ++$paragraph) {
            $sentences = [];

            for ($sentence = 0; $sentence < self::SENTENCES_PER_PARAGRAPH; ++$sentence) {
                $offset = $index + $paragraph * self::SENTENCES_PER_PARAGRAPH + $sentence * 5;
                $sentences[] = self::SENTENCES[$offset % \count(self::SENTENCES)];
            }

            $paragraphs[] = implode(' ', $sentences);
        }

        return implode("\n\n", $paragraphs);
    }
}
