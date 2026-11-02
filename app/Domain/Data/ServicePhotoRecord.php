<?php

declare(strict_types=1);

namespace App\Domain\Data;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Routing\UrlRoutable;

/**
 * Photo metadata (F-18, F-37). The binary always lives on the private disk;
 * the database/file store only knows where.
 */
final class ServicePhotoRecord implements UrlRoutable
{
    public function __construct(
        public readonly int $id,
        public readonly int $serviceId,
        public readonly string $disk,
        public readonly string $path,
        public readonly string $thumbPath,
        public readonly string $mime,
        public readonly int $size,
        public readonly int $width,
        public readonly int $height,
        public readonly string $checksum,
        public readonly int $uploadedBy,
        public readonly CarbonImmutable $createdAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: is_numeric($data['id'] ?? null) ? (int) $data['id'] : 0,
            serviceId: is_numeric($data['service_id'] ?? null) ? (int) $data['service_id'] : 0,
            disk: (string) ($data['disk'] ?? 'local'),
            path: (string) ($data['path'] ?? ''),
            thumbPath: (string) ($data['thumb_path'] ?? ''),
            mime: (string) ($data['mime'] ?? 'image/jpeg'),
            size: is_numeric($data['size'] ?? null) ? (int) $data['size'] : 0,
            width: is_numeric($data['width'] ?? null) ? (int) $data['width'] : 0,
            height: is_numeric($data['height'] ?? null) ? (int) $data['height'] : 0,
            checksum: (string) ($data['checksum'] ?? ''),
            uploadedBy: is_numeric($data['uploaded_by'] ?? null) ? (int) $data['uploaded_by'] : 0,
            createdAt: self::toDate($data['created_at'] ?? null) ?? CarbonImmutable::now('UTC'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->serviceId,
            'disk' => $this->disk,
            'path' => $this->path,
            'thumb_path' => $this->thumbPath,
            'mime' => $this->mime,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'checksum' => $this->checksum,
            'uploaded_by' => $this->uploadedBy,
            'created_at' => $this->createdAt->utc()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return self::fromArray(array_merge($this->toArray(), $changes));
    }

    public function withId(int $id): self
    {
        return $this->with(['id' => $id]);
    }

    public function getRouteKey(): int
    {
        return $this->id;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return null;
    }

    public function resolveChildRouteBinding($childType, $value, $field): ?self
    {
        return null;
    }

    private static function toDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value->utc();
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value, 'UTC')->utc();
    }
}
