<?php

declare(strict_types=1);

namespace App\Console\Commands;

use GdImage;
use Illuminate\Console\Command;

/**
 * `php artisan icons:generate` — PWA icons derived from the real brand mark
 * (public/brand/studio-komp-mark.png) with GD only (decision D12).
 *
 * The "any" variants are the mark scaled to the full canvas. The "maskable"
 * variants keep the mark inside an 80% safe zone on a graphite square, so
 * Android may crop the icon without cutting the logo.
 */
final class GenerateIcons extends Command
{
    protected $signature = 'icons:generate';

    protected $description = 'Generuje ikony PWA (96/192/512 + maskable) z logo marki przy pomocy GD';

    /** Graphite #2d343a — brand chrome and the maskable canvas. */
    private const GRAPHITE = [45, 52, 58];

    /** Paper #ffffff — flattening colour for the full-bleed "any" icons. */
    private const WHITE = [255, 255, 255];

    /** The mark fills at most 80% of a maskable canvas (>= 10% padding per side). */
    private const MASKABLE_MARK_RATIO = 0.8;

    /** Icon sizes the manifest and `icons:check` expect. */
    private const SIZES = [96, 192, 512];

    /** Only the launcher sizes need a maskable variant (the manifest lists these). */
    private const MASKABLE_SIZES = [192, 512];

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('Rozszerzenie GD nie jest dostępne w tym PHP — nie można wygenerować ikon PWA.');

            return self::FAILURE;
        }

        $sourcePath = public_path('brand/studio-komp-mark.png');

        if (! is_file($sourcePath)) {
            $this->error('Brak znaku marki: public/brand/studio-komp-mark.png');
            $this->line('Wgraj oficjalny znak Studio-Komp i uruchom polecenie ponownie.');

            return self::FAILURE;
        }

        $mark = @imagecreatefrompng($sourcePath);

        if (! $mark instanceof GdImage) {
            $this->error('Nie można odczytać znaku marki: public/brand/studio-komp-mark.png (niepoprawny PNG?)');

            return self::FAILURE;
        }

        $directory = public_path('icons');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error('Nie można utworzyć katalogu '.$directory);
            imagedestroy($mark);

            return self::FAILURE;
        }

        try {
            foreach (self::SIZES as $size) {
                $this->line('OK '.$this->writeIcon($directory, $mark, $size, maskable: false));
            }

            foreach (self::MASKABLE_SIZES as $size) {
                $this->line('OK '.$this->writeIcon($directory, $mark, $size, maskable: true));
            }
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            imagedestroy($mark);
        }

        $this->info('Ikony PWA zapisane w public/icons.');

        return self::SUCCESS;
    }

    /**
     * Renders one icon and returns "file, dimensions, size" for the summary.
     */
    private function writeIcon(string $directory, GdImage $mark, int $size, bool $maskable): string
    {
        $canvas = imagecreatetruecolor($size, $size);

        if ($canvas === false) {
            throw new \RuntimeException(sprintf('GD nie potrafi utworzyć obrazu %dx%d.', $size, $size));
        }

        $file = $maskable
            ? sprintf('icons/icon-%d-maskable.png', $size)
            : sprintf('icons/icon-%d.png', $size);
        $path = $directory.'/'.basename($file);

        try {
            // An opaque background flattens any transparency in the source mark,
            // so the icon never shows holes or a black fringe in a launcher.
            imagesavealpha($canvas, false);
            imagealphablending($canvas, true);

            [$red, $green, $blue] = $maskable ? self::GRAPHITE : self::WHITE;
            $background = imagecolorallocate($canvas, $red, $green, $blue);
            imagefilledrectangle($canvas, 0, 0, $size - 1, $size - 1, $background);

            // Maskable icons need the safe zone; "any" icons are the mark itself.
            $markSize = $maskable ? (int) floor($size * self::MASKABLE_MARK_RATIO) : $size;
            $offset = intdiv($size - $markSize, 2);

            $this->drawMark($mark, $canvas, $offset, $markSize);

            if (! imagepng($canvas, $path, 9)) {
                throw new \RuntimeException('Nie można zapisać pliku '.$file.'.');
            }
        } finally {
            imagedestroy($canvas);
        }

        return sprintf('%s (%dx%d, %d B)', $file, $size, $size, (int) filesize($path));
    }

    /**
     * Draws the mark into a square of $markSize pixels at ($offset, $offset).
     */
    private function drawMark(GdImage $mark, GdImage $canvas, int $offset, int $markSize): void
    {
        $staged = $this->halveToFit($mark, $markSize);

        try {
            $drawn = imagecopyresampled(
                $canvas,
                $staged,
                $offset,
                $offset,
                0,
                0,
                $markSize,
                $markSize,
                imagesx($staged),
                imagesy($staged),
            );

            if (! $drawn) {
                throw new \RuntimeException('GD nie potrafi przeskalować znaku marki.');
            }
        } finally {
            if ($staged !== $mark) {
                imagedestroy($staged);
            }
        }
    }

    /**
     * Downscales the mark in half steps until one last resample is enough.
     * imagecopyresampled averages whole source pixels, so a single 512 -> 96
     * jump would erase the thin 2-3 px laptop outline; halving keeps it legible.
     */
    private function halveToFit(GdImage $mark, int $target): GdImage
    {
        $width = imagesx($mark);
        $height = imagesy($mark);
        $current = $mark;

        while (intdiv($width, 2) >= $target && intdiv($height, 2) >= $target) {
            $nextWidth = max($target, intdiv($width + 1, 2));
            $nextHeight = max($target, intdiv($height + 1, 2));

            $copy = imagecreatetruecolor($nextWidth, $nextHeight);

            if ($copy === false) {
                if ($current !== $mark) {
                    imagedestroy($current);
                }

                throw new \RuntimeException('GD nie potrafi utworzyć obrazu pośredniego.');
            }

            imagealphablending($copy, false);
            imagesavealpha($copy, true);
            imagecopyresampled($copy, $current, 0, 0, 0, 0, $nextWidth, $nextHeight, $width, $height);

            if ($current !== $mark) {
                imagedestroy($current);
            }

            $current = $copy;
            $width = $nextWidth;
            $height = $nextHeight;
        }

        return $current;
    }
}
