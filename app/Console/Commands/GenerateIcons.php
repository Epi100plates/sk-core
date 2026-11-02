<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * `php artisan icons:generate` — PWA icons drawn with GD (decision D12).
 * Replaces the Python script from the old project; no external tools needed.
 */
final class GenerateIcons extends Command
{
    protected $signature = 'icons:generate';

    protected $description = 'Generuje ikony PWA (96/192/512 + maskable) prosto z GD';

    public function handle(): int
    {
        $directory = public_path('icons');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error('Nie można utworzyć katalogu '.$directory);

            return self::FAILURE;
        }

        foreach ([96, 192, 512] as $size) {
            $this->writeIcon($directory, $size, maskable: false);
            $this->writeIcon($directory, $size, maskable: true);
        }

        $this->info('Ikony PWA zapisane w public/icons.');

        return self::SUCCESS;
    }

    private function writeIcon(string $directory, int $size, bool $maskable): void
    {
        $image = imagecreatetruecolor($size, $size);

        if ($image === false) {
            throw new \RuntimeException('GD nie potrafi utworzyć obrazu.');
        }

        imagesavealpha($image, true);
        $background = imagecolorallocate($image, 13, 59, 102);
        imagefilledrectangle($image, 0, 0, $size, $size, $background);

        // Maskable icons need a 20% safe zone so Android can crop them.
        $scale = $maskable ? 0.62 : 0.78;
        $white = imagecolorallocate($image, 255, 255, 255);
        $center = $size / 2;

        // A stylised screwdriver: round head plus an angled handle.
        $headRadius = (int) ($size * $scale * 0.18);
        imagefilledellipse($image, (int) $center, (int) ($center - $size * 0.02), $headRadius * 2, $headRadius * 2, $white);

        $handleWidth = (int) ($size * $scale * 0.12);
        $handleHeight = (int) ($size * $scale * 0.34);
        imagefilledrectangle(
            $image,
            (int) ($center - $handleWidth / 2),
            (int) ($center + $headRadius * 0.7),
            (int) ($center + $handleWidth / 2),
            (int) ($center + $headRadius * 0.7 + $handleHeight),
            $white,
        );

        $name = $maskable
            ? sprintf('%s/icon-%d-maskable.png', $directory, $size)
            : sprintf('%s/icon-%d.png', $directory, $size);

        imagepng($image, $name);
        imagedestroy($image);
    }
}
