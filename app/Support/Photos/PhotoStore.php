<?php

declare(strict_types=1);

namespace App\Support\Photos;

use App\Domain\Data\ServicePhotoRecord;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Stores laptop photos on the private disk (F-18, F-37, defects D-52/D-28:
 * photos used to be public files served straight from the web root).
 *
 * Every upload is decoded and re-encoded with GD, which strips EXIF data and
 * destroys polyglot files (a "photo" that is also a PHP script). A 400 px
 * thumbnail is generated for the gallery, so the queue stays light.
 */
final class PhotoStore
{
    public const DISK = 'local';

    public const MAX_FILES = 10;

    public const MAX_BYTES = 10 * 1024 * 1024;

    public const THUMB_WIDTH = 400;

    private const JPEG_QUALITY = 88;

    public function store(int $serviceId, UploadedFile $file, int $uploadedBy): ServicePhotoRecord
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new InvalidArgumentException('Nie można odczytać przesłanego pliku.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('Zdjęcie jest za duże (limit 10 MB).');
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            throw new InvalidArgumentException('Przesłany plik jest pusty.');
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw new InvalidArgumentException('Plik nie jest poprawnym zdjęciem.');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $encoded = $this->encode($image);
        $thumb = $this->thumbnail($image);

        imagedestroy($image);

        $name = bin2hex(random_bytes(16));
        $relativePath = sprintf('photos/%d/%s.jpg', $serviceId, $name);
        $relativeThumb = sprintf('photos/%d/thumbs/%s.jpg', $serviceId, $name);

        $disk = Storage::disk(self::DISK);
        $disk->put($relativePath, $encoded);
        $disk->put($relativeThumb, $thumb);

        return new ServicePhotoRecord(
            id: 0,
            serviceId: $serviceId,
            disk: self::DISK,
            path: $relativePath,
            thumbPath: $relativeThumb,
            mime: 'image/jpeg',
            size: strlen($encoded),
            width: $width,
            height: $height,
            checksum: hash('sha256', $encoded),
            uploadedBy: $uploadedBy,
            createdAt: CarbonImmutable::now('UTC'),
        );
    }

    public function delete(ServicePhotoRecord $photo): void
    {
        $disk = Storage::disk($photo->disk);

        foreach ([$photo->path, $photo->thumbPath] as $relative) {
            if ($relative !== '' && $disk->exists($relative)) {
                $disk->delete($relative);
            }
        }

        $directory = 'photos/'.$photo->serviceId;
        $remaining = $disk->allFiles($directory);

        // Remove the now empty directory so `photos:verify` never reports ghosts.
        if ($remaining === [] && $disk->exists($directory)) {
            $disk->deleteDirectory($directory);
        }
    }

    public function read(ServicePhotoRecord $photo, bool $thumb = false): ?string
    {
        $relative = $thumb && $photo->thumbPath !== '' ? $photo->thumbPath : $photo->path;
        $disk = Storage::disk($photo->disk);

        if (! $disk->exists($relative)) {
            return null;
        }

        return $disk->get($relative);
    }

    public function exists(ServicePhotoRecord $photo, bool $thumb = false): bool
    {
        $relative = $thumb && $photo->thumbPath !== '' ? $photo->thumbPath : $photo->path;

        return $relative !== '' && Storage::disk($photo->disk)->exists($relative);
    }

    /** Absolute path, used by the photo verifier. */
    public function absolutePath(ServicePhotoRecord $photo, bool $thumb = false): string
    {
        $relative = $thumb && $photo->thumbPath !== '' ? $photo->thumbPath : $photo->path;

        return Storage::disk($photo->disk)->path($relative);
    }

    /**
     * @param  \GdImage  $image
     */
    private function encode(mixed $image): string
    {
        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);
        $encoded = ob_get_clean();

        if (! is_string($encoded) || $encoded === '') {
            throw new InvalidArgumentException('Nie udało się przetworzyć zdjęcia.');
        }

        return $encoded;
    }

    /**
     * @param  \GdImage  $image
     */
    private function thumbnail(mixed $image): string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $targetWidth = min(self::THUMB_WIDTH, $width);
        $targetHeight = max(1, (int) round($height * ($targetWidth / $width)));

        $thumb = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($thumb === false) {
            throw new InvalidArgumentException('Nie udało się utworzyć miniatury.');
        }

        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        $encoded = $this->encode($thumb);
        imagedestroy($thumb);

        return $encoded;
    }
}
