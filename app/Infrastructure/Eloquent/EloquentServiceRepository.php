<?php

declare(strict_types=1);

namespace App\Infrastructure\Eloquent;

use App\Contracts\ServiceRepository;
use App\Domain\Data\Page;
use App\Domain\Data\ServiceQuery;
use App\Domain\Data\ServiceRecord;
use App\Domain\Exceptions\DataStoreException;
use App\Domain\Exceptions\VersionConflictException;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * MySQL implementation of the service repository (decision D13). Behaves exactly
 * like `Infrastructure\Json\JsonServiceRepository`; the shared test suite runs
 * against both.
 */
final class EloquentServiceRepository implements ServiceRepository
{
    public function find(int $id): ?ServiceRecord
    {
        $model = Service::query()->find($id);

        return $model instanceof Service ? self::toRecord($model) : null;
    }

    public function findByReference(string $reference): ?ServiceRecord
    {
        $model = Service::query()->where('reference', $reference)->first();

        return $model instanceof Service ? self::toRecord($model) : null;
    }

    public function save(ServiceRecord $service): ServiceRecord
    {
        if ($service->id === 0) {
            $model = new Service;
            $model->fill(self::attributes($service, create: true));
            $model->version = 0;
            $model->save();

            return self::toRecord($model->refresh());
        }

        return DB::transaction(function () use ($service): ServiceRecord {
            $model = Service::query()->lockForUpdate()->find($service->id);

            if (! $model instanceof Service) {
                throw new DataStoreException('Zlecenie #'.$service->id.' nie istnieje już w magazynie danych.');
            }

            if ((int) $model->version !== $service->version) {
                throw new VersionConflictException($service->id);
            }

            $model->fill(self::attributes($service, create: false));
            $model->version = $service->version + 1;
            $model->save();

            return self::toRecord($model->refresh());
        });
    }

    public function paginate(ServiceQuery $query): Page
    {
        $builder = Service::query();

        if ($query->statusValues() !== []) {
            $builder->whereIn('status', $query->statusValues());
        }

        if ($query->releasedFrom instanceof CarbonImmutable) {
            $builder->where('released_at', '>=', $query->releasedFrom->utc()->format('Y-m-d H:i:s'));
        }

        if ($query->releasedTo instanceof CarbonImmutable) {
            $builder->where('released_at', '<=', $query->releasedTo->utc()->format('Y-m-d H:i:s'));
        }

        if ($query->hasSearchTerm()) {
            $term = '%'.self::escapeLike(trim((string) $query->term)).'%';

            $builder->where(function (Builder $inner) use ($term): void {
                foreach (['phone', 'phone_normalized', 'seal_number', 'device_model', 'serial_number', 'issue', 'reference'] as $column) {
                    $inner->orWhere($column, 'like', $term);
                }
            });
        }

        $total = (int) (clone $builder)->count();

        $this->applySort($builder, $query->sort);

        $models = $builder
            ->forPage($query->page, $query->perPage)
            ->get();

        $items = [];

        foreach ($models as $model) {
            $items[] = self::toRecord($model);
        }

        return Page::of($items, $total, $query->perPage, $query->page);
    }

    public function recentlyReleased(int $limit = 10): array
    {
        $models = Service::query()
            ->where('status', 'released')
            ->orderByDesc('released_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $items = [];

        foreach ($models as $model) {
            $items[] = self::toRecord($model);
        }

        return $items;
    }

    public function countsByStatus(): array
    {
        /** @var array<string, int> $counts */
        $counts = [];

        $rows = Service::query()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            /** @var object{status: string, aggregate: int|string} $row */
            $counts[(string) $row->status] = (int) $row->aggregate;
        }

        return $counts;
    }

    public function count(): int
    {
        return (int) Service::query()->count();
    }

    public function all(): array
    {
        $items = [];

        foreach (Service::query()->orderBy('id')->get() as $model) {
            $items[] = self::toRecord($model);
        }

        return $items;
    }

    public function nextReference(int $year): string
    {
        $prefix = sprintf('SRV-%d-', $year);

        $last = Service::query()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = 1;

        if (is_string($last) && $last !== '') {
            $suffix = substr($last, strlen($prefix));

            if (ctype_digit($suffix)) {
                $sequence = (int) $suffix + 1;
            }
        }

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  Builder<Service>  $builder
     */
    private function applySort(Builder $builder, string $mode): void
    {
        if ($mode === ServiceQuery::SORT_RELEASED) {
            $builder->orderByDesc('released_at')->orderByDesc('id');

            return;
        }

        if ($mode === ServiceQuery::SORT_ACCEPTED) {
            $builder->orderByDesc('accepted_at')->orderByDesc('id');

            return;
        }

        // Final states always last, then status priority, express first, then
        // the promised date — identical to the JSON store (F-22, defect D-46).
        $builder->orderByRaw("CASE WHEN status IN ('released','cancelled') THEN 1 ELSE 0 END")
            ->orderByRaw(
                "CASE status
                    WHEN 'in_progress' THEN 1
                    WHEN 'pending' THEN 2
                    WHEN 'awaiting_parts' THEN 3
                    WHEN 'ready_for_pickup' THEN 4
                    WHEN 'released' THEN 5
                    ELSE 6 END"
            )
            ->orderByDesc('is_express')
            ->orderBy('planned_at')
            ->orderBy('id');
    }

    private static function toRecord(Service $model): ServiceRecord
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $model->attributesToArray();

        return ServiceRecord::fromArray($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private static function attributes(ServiceRecord $service, bool $create): array
    {
        $attributes = $service->toArray();

        unset($attributes['id'], $attributes['version'], $attributes['created_at'], $attributes['updated_at'], $attributes['deleted_at']);

        if (! $create) {
            unset($attributes['accepted_at']);
        }

        return $attributes;
    }

    private static function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}
