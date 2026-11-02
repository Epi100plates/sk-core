<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * `php artisan icons:check` — validates the manifest icons (defect D-29: three
 * of four icons were 404 while the old verify script reported success).
 */
final class CheckIcons extends Command
{
    protected $signature = 'icons:check';

    protected $description = 'Sprawdza, czy wszystkie ikony PWA istnieją i mają właściwe wymiary';

    /** @var array<string, int> */
    private const EXPECTED = [
        'icon-96.png' => 96,
        'icon-192.png' => 192,
        'icon-192-maskable.png' => 192,
        'icon-512.png' => 512,
        'icon-512-maskable.png' => 512,
    ];

    public function handle(): int
    {
        $problems = 0;

        foreach (self::EXPECTED as $file => $expectedSize) {
            $path = public_path('icons/'.$file);

            if (! is_file($path)) {
                $this->error('Brak pliku: icons/'.$file);
                $problems++;

                continue;
            }

            $size = @getimagesize($path);

            if ($size === false) {
                $this->error('Niepoprawny obraz: icons/'.$file);
                $problems++;

                continue;
            }

            if ($size[0] !== $expectedSize || $size[1] !== $expectedSize) {
                $this->error(sprintf('Zły rozmiar %s: %dx%d (oczekiwano %d)', $file, $size[0], $size[1], $expectedSize));
                $problems++;

                continue;
            }

            $this->line('OK icons/'.$file);
        }

        if ($problems > 0) {
            $this->error('Uruchom `php artisan icons:generate`.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
