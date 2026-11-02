<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent;

use App\Contracts\ServiceEventRepository;
use App\Domain\Data\Page;
use App\Domain\Data\ServiceEventRecord;
use App\Models\ServiceEvent;
use Carbon\CarbonImmutable;

final class EloquentServiceEventRepository implements ServiceEventRepository
{
    public function append(ServiceEventRecord $event): ServiceEventRecord
    {
        $model = new ServiceEvent;
        $model->fill([
            'service_id' => $event->serviceId,
            'type' => $event->type->value,
            'actor_id' => $event->actorId,
            'from_status' => $event->fromStatus,
            'to_status' => $event->toStatus,
            'changes' => $event->changes === [] ? null : $event->changes,
            'description' => $event->description,
            'ip' => $event->ip,
            'user_agent' => $event->userAgent,
        ]);
        $model->created_at = $event->createdAt->utc()->format('Y-m-d H:i:s');
        $model->save();

        return self::toRecord($model->refresh());
    }

    public function forService(int $serviceId, int $perPage = 20, int $page = 1): Page
    {
        $builder = ServiceEvent::query()->where('service_id', $serviceId);

        $total = (int) (clone $builder)->count();

        $models = $builder
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        $items = [];

        foreach ($models as $model) {
            $items[] = self::toRecord($model);
        }

        return Page::of($items, $total, $perPage, $page);
    }

    public function all(): array
    {
        $items = [];

        foreach (ServiceEvent::query()->orderBy('id')->get() as $model) {
            $items[] = self::toRecord($model);
        }

        return $items;
    }

    public function count(): int
    {
        return (int) ServiceEvent::query()->count();
    }

    private static function toRecord(ServiceEvent $model): ServiceEventRecord
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $model->attributesToArray();
        $attributes['created_at'] = $model->created_at instanceof CarbonImmutable
            ? $model->created_at->toDateTimeString()
            : $attributes['created_at'] ?? null;

        return ServiceEventRecord::fromArray($attributes);
    }
}
