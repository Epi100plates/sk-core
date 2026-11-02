<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent;

use App\Contracts\ServicePhotoRepository;
use App\Domain\Data\ServicePhotoRecord;
use App\Models\ServicePhoto;

final class EloquentServicePhotoRepository implements ServicePhotoRepository
{
    public function find(int $id): ?ServicePhotoRecord
    {
        $model = ServicePhoto::query()->find($id);

        return $model instanceof ServicePhoto ? self::toRecord($model) : null;
    }

    public function save(ServicePhotoRecord $photo): ServicePhotoRecord
    {
        if ($photo->id === 0) {
            $model = new ServicePhoto;
            $model->fill(self::attributes($photo));
            $model->created_at = $photo->createdAt->utc()->format('Y-m-d H:i:s');
            $model->save();

            return self::toRecord($model->refresh());
        }

        $model = ServicePhoto::query()->findOrFail($photo->id);
        $model->fill(self::attributes($photo));
        $model->save();

        return self::toRecord($model->refresh());
    }

    public function forService(int $serviceId): array
    {
        $items = [];

        foreach (ServicePhoto::query()->where('service_id', $serviceId)->orderBy('id')->get() as $model) {
            $items[] = self::toRecord($model);
        }

        return $items;
    }

    public function delete(int $id): void
    {
        ServicePhoto::query()->whereKey($id)->delete();
    }

    public function all(): array
    {
        $items = [];

        foreach (ServicePhoto::query()->orderBy('id')->get() as $model) {
            $items[] = self::toRecord($model);
        }

        return $items;
    }

    public function count(): int
    {
        return (int) ServicePhoto::query()->count();
    }

    private static function toRecord(ServicePhoto $model): ServicePhotoRecord
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $model->attributesToArray();

        return ServicePhotoRecord::fromArray($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private static function attributes(ServicePhotoRecord $photo): array
    {
        $attributes = $photo->toArray();

        unset($attributes['id'], $attributes['created_at']);

        return $attributes;
    }
}
