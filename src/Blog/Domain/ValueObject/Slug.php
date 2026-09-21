<?php

declare(strict_types=1);

namespace App\Blog\Domain\ValueObject;

/**
 * Человекочитаемый идентификатор в адресе страницы.
 *
 * Слаг — часть публичного контракта: по нему открывается статья или категория,
 * поэтому формат проверяется в конструкторе, а не в контроллере.
 */
final readonly class Slug implements \Stringable
{
    private const int MAX_LENGTH = 200;

    private const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Заголовки у нас русские, а слаг обязан быть латиницей,
     * поэтому транслитерация нужна своя — intl в требованиях нет.
     */
    private const array TRANSLITERATION = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ('' === $value) {
            throw new \InvalidArgumentException('Слаг не может быть пустым.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Слаг длиннее %d символов.', self::MAX_LENGTH));
        }

        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new \InvalidArgumentException(sprintf('Слаг «%s» должен состоять из строчных латинских букв, цифр и дефисов.', $value));
        }

        return new self($value);
    }

    /**
     * Строит слаг из заголовка. Результат прогоняется через fromString,
     * поэтому правила формата описаны ровно в одном месте.
     */
    public static function fromTitle(string $title): self
    {
        $slug = mb_strtolower(trim($title));
        $slug = strtr($slug, self::TRANSLITERATION);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        if (mb_strlen($slug) > self::MAX_LENGTH) {
            // Режем по границе слова, чтобы не оставлять обрубок вроде «...-katego».
            $slug = mb_substr($slug, 0, self::MAX_LENGTH);
            $lastDash = mb_strrpos($slug, '-');

            if (false !== $lastDash && $lastDash > 0) {
                $slug = mb_substr($slug, 0, $lastDash);
            }
        }

        if ('' === $slug) {
            throw new \InvalidArgumentException(sprintf('Из заголовка «%s» не получается слаг.', $title));
        }

        return new self($slug);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
