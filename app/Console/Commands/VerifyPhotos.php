<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\ServicePhotoRepository;
use App\Domain\Data\ServicePhotoRecord;
use App\Support\Photos\PhotoStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * `php artisan photos:verify [--fix]` — replaces the two broken legacy scripts
 * (feature F-54, defects D-29/D-52). It reports records without files, files
 * without records and missing thumbnails; `--fix` quarantines orphan files and
 * regenerates missing thumbnails instead of deleting anything.
 */
final class VerifyPhotos extends Command
{
    protected $signature = 'photos:verify {--fix : Przenieś osierocone pliki do kwarantanny i odtwórz miniatury}';

    protected $description = 'Sprawdza spójność metadanych zdjęć z plikami na dysku';

    public function handle(ServicePhotoRepository $photos): int
    {
        $records = $photos->all();
        $problems = 0;
        $fixed = 0;

        $knownPaths = [];

        foreach ($records as $photo) {
            $knownPaths[$photo->path] = true;
            $knownPaths[$photo->thumbPath] = true;

            if (! Storage::disk($photo->disk)->exists($photo->path)) {
                $this->error(sprintf('Rekord #%d bez pliku: %s', $photo->id, $photo->path));
                $problems++;

                continue;
            }

            if (! Storage::disk($photo->disk)->exists($photo->thumbPath)) {
                $this->warn(sprintf('Brak miniatury dla zdjęcia #%d (%s)', $photo->id, $photo->path));

                if ($this->option('fix')) {
                    $this->regenerateThumbnail($photo);
                    $fixed++;
                } else {
                    $problems++;
                }
            }
        }

        foreach (Storage::disk(PhotoStore::DISK)->allFiles('photos') as $file) {
            if (str_ends_with($file, '.tmp') || isset($knownPaths[$file])) {
                continue;
            }

            $this->warn('Plik bez rekordu: '.$file);

            if ($this->option('fix')) {
                $target = 'quarantine/'.now()->utc()->format('YmdHis').'/'.basename($file);
                Storage::disk(PhotoStore::DISK)->move($file, $target);
                $this->line('  → przeniesiono do '.$target);
                $fixed++;
            } else {
                $problems++;
            }
        }

        if ($problems === 0) {
            $this->info(sprintf('Zdjęcia spójne (%d rekordów). Naprawiono: %d.', count($records), $fixed));

            return self::SUCCESS;
        }

        $this->error(sprintf('Znaleziono problemów: %d. Uruchom z --fix, aby naprawić część z nich.', $problems));

        return self::FAILURE;
    }

    private function regenerateThumbnail(ServicePhotoRecord $photo): void
    {
        $original = Storage::disk($photo->disk)->get($photo->path);

        if ($original === null) {
            return;
        }

        $image = @imagecreatefromstring($original);

        if ($image === false) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $targetWidth = min(PhotoStore::THUMB_WIDTH, $width);
        $targetHeight = max(1, (int) round($height * ($targetWidth / $width)));

        $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($thumb, null, 88);
        $encoded = ob_get_clean();

        imagedestroy($thumb);
        imagedestroy($image);

        if (is_string($encoded) && $encoded !== '') {
            Storage::disk($photo->disk)->put($photo->thumbPath, $encoded);
        }
    }
}
