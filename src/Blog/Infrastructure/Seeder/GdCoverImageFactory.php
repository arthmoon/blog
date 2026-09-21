<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Seeder;

use App\Blog\Application\Port\CoverImageFactory;
use App\Blog\Domain\ValueObject\ImagePath;
use App\Blog\Domain\ValueObject\Slug;

/**
 * Рисует обложку через GD и кладёт файл в каталог загрузок.
 *
 * Картинка абстрактная, без текста: подписать её по-русски можно только
 * через imagettftext, а это тянет за собой шрифт в репозиторий. Цвет и
 * раскладка полос выводятся из заголовка, поэтому у каждой статьи своя
 * обложка, и при повторном запуске сидера она та же самая.
 *
 * Скачивать заглушки из сети сидер не должен: наполнение базы обязано
 * работать без интернета.
 */
final readonly class GdCoverImageFactory implements CoverImageFactory
{
    private const int WIDTH = 800;

    private const int HEIGHT = 450;

    private const string SUBDIRECTORY = 'posts';

    public function __construct(private string $uploadsPath)
    {
    }

    public function create(string $title): ImagePath
    {
        $relative = sprintf('%s/%s.png', self::SUBDIRECTORY, Slug::fromTitle($title)->value);
        $absolute = $this->uploadsPath . '/' . $relative;

        $this->ensureDirectory(\dirname($absolute));

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if (false === $image) {
            throw new \RuntimeException('GD не смог создать изображение.');
        }

        try {
            $this->draw($image, crc32($title));

            if (!imagepng($image, $absolute, 6)) {
                throw new \RuntimeException(sprintf('Не удалось записать обложку %s.', $absolute));
            }
        } finally {
            imagedestroy($image);
        }

        return ImagePath::fromString($relative);
    }

    private function draw(\GdImage $image, int $seed): void
    {
        $hue = $seed % 360;

        // Палитра приглушённая: обложка стоит в карточке рядом с текстом
        // и не должна перетягивать внимание на себя.
        imagefilledrectangle(
            $image,
            0,
            0,
            self::WIDTH,
            self::HEIGHT,
            $this->color($image, $hue, 0.22, 0.24),
        );

        // Три широкие диагональные полосы одного тона с небольшой разницей
        // в светлоте: узнаваемый рисунок без шрифта и внешних файлов.
        for ($stripe = 0; $stripe < 3; ++$stripe) {
            $shift = (int) (self::WIDTH / 3) * $stripe + $seed % 90 - 120;

            imagefilledpolygon(
                $image,
                [
                    $shift, 0,
                    $shift + 210, 0,
                    $shift + 90, self::HEIGHT,
                    $shift - 120, self::HEIGHT,
                ],
                $this->color($image, ($hue + 8 * $stripe) % 360, 0.20, 0.29 + 0.045 * $stripe),
            );
        }
    }

    private function color(\GdImage $image, int $hue, float $saturation, float $lightness): int
    {
        [$red, $green, $blue] = self::hslToRgb($hue / 360, $saturation, $lightness);

        $color = imagecolorallocate($image, $red, $green, $blue);

        if (false === $color) {
            throw new \RuntimeException('GD не смог выделить цвет.');
        }

        return $color;
    }

    /**
     * @return array{int, int, int}
     */
    private static function hslToRgb(float $hue, float $saturation, float $lightness): array
    {
        if (0.0 === $saturation) {
            $value = (int) round($lightness * 255);

            return [$value, $value, $value];
        }

        $q = $lightness < 0.5
            ? $lightness * (1 + $saturation)
            : $lightness + $saturation - $lightness * $saturation;
        $p = 2 * $lightness - $q;

        return [
            (int) round(self::channel($p, $q, $hue + 1 / 3) * 255),
            (int) round(self::channel($p, $q, $hue) * 255),
            (int) round(self::channel($p, $q, $hue - 1 / 3) * 255),
        ];
    }

    private static function channel(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            ++$t;
        }

        if ($t > 1) {
            --$t;
        }

        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }

        if ($t < 1 / 2) {
            return $q;
        }

        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0o775, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Не удалось создать каталог %s.', $path));
        }
    }
}
