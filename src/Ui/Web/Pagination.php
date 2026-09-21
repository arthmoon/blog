<?php

declare(strict_types=1);

namespace App\Ui\Web;

/**
 * Номера страниц для навигации с окном вокруг текущей.
 *
 * При двух-трёх страницах разницы нет, но категория на тысячу статей
 * выдала бы восемьдесят три ссылки подряд. Показываем первую, последнюю,
 * соседей текущей и многоточие вместо пропусков.
 */
final readonly class Pagination
{
    /**
     * null в списке — это разрыв, на его месте рисуется многоточие.
     *
     * @return list<int|null>
     */
    public static function windowed(int $current, int $pageCount, int $radius = 2): array
    {
        if ($pageCount < 1) {
            throw new \InvalidArgumentException(sprintf('Страниц не может быть меньше одной, получено %d.', $pageCount));
        }

        $visible = [1, $pageCount];

        for ($page = $current - $radius; $page <= $current + $radius; ++$page) {
            if ($page >= 1 && $page <= $pageCount) {
                $visible[] = $page;
            }
        }

        $visible = array_values(array_unique($visible));
        sort($visible);

        $result = [];
        $previous = 0;

        foreach ($visible as $page) {
            // Разрыв ровно в одну страницу многоточием не заменяем:
            // «1 … 3» занимает столько же места, сколько «1 2 3».
            if ($page - $previous > 1) {
                $result[] = $previous > 0 && $page - $previous === 2 ? $previous + 1 : null;
            }

            $result[] = $page;
            $previous = $page;
        }

        return $result;
    }
}
