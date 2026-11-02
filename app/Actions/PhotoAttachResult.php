<?php

declare(strict_types=1);

namespace App\Actions;

use App\Domain\Data\ServicePhotoRecord;

final class PhotoAttachResult
{
    /**
     * @param  list<ServicePhotoRecord>  $photos
     * @param  list<string>  $failed
     */
    public function __construct(
        public readonly array $photos,
        public readonly array $failed,
    ) {}

    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }

    public function addedCount(): int
    {
        return count($this->photos);
    }
}
