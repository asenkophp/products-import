<?php

declare(strict_types=1);

namespace App\DataFixtures;

use RuntimeException;

/** Генерирует цветные PNG-заглушки для фикстур, чтобы галерея работала без интернета. */
final class PlaceholderImageGenerator
{
    public function __construct(
        private readonly string $directory,
        private readonly string $publicPrefix,
    ) {}

    /** @return string публичный путь к файлу */
    public function generate(string $name, string $label, int $hue): string
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Не удалось создать папку для картинок: ' . $this->directory);
        }

        $image = imagecreatetruecolor(600, 600);
        if ($image === false) {
            throw new RuntimeException('GD не смог создать изображение');
        }

        [$r, $g, $b] = self::hslToRgb($hue % 360, 0.45, 0.75);
        $background  = (int) imagecolorallocate($image, $r, $g, $b);
        [$r, $g, $b] = self::hslToRgb($hue % 360, 0.45, 0.35);
        $foreground  = (int) imagecolorallocate($image, $r, $g, $b);

        imagefill($image, 0, 0, $background);
        imagefilledellipse($image, 300, 260, 260, 260, $foreground);
        $x = (int) max(10, 300 - strlen($label) * 9 / 2);
        imagestring($image, 5, $x, 450, $label, $foreground);

        $file = $this->directory . '/' . $name . '.png';
        imagepng($image, $file);
        imagedestroy($image);

        return $this->publicPrefix . '/' . $name . '.png';
    }

    /** @return array{int, int, int} */
    private static function hslToRgb(int $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (intdiv($h, 60)) {
            0       => [$c, $x, 0],
            1       => [$x, $c, 0],
            2       => [0, $c, $x],
            3       => [0, $x, $c],
            4       => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return [(int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255)];
    }
}
