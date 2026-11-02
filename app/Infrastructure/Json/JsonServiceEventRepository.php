<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

use App\Contracts\ServiceEventRepository;
use App\Domain\Data\Page;
use App\Domain\Data\ServiceEventRecord;
use Carbon\CarbonImmutable;

final class JsonServiceEventRepository implements ServiceEventRepository
{
    use ReadsJsonCollections;

    private const COLLECTION = 'service_events';

    public function __construct(private readonly JsonStore $store) {}

    public function append(ServiceEventRecord $event): ServiceEventRecord
    {
        return $this->store->withTransaction(function (JsonTransaction $transaction) use ($event): ServiceEventRecord {
            $rows = $transaction->rows(self::COLLECTION);
            $stored = $event->withId($transaction->nextId(self::COLLECTION))
                ->toArray();
            $stored['created_at'] = $event->createdAt->utc()->format('Y-m-d H:i:s');

            $transaction->replace(self::COLLECTION, [...$rows, $stored]);

            return ServiceEventRecord::fromArray($stored);
        });
    }

    public function forService(int $serviceId, int $perPage = 20, int $page = 1): Page
    {
        $rows = [];

        foreach ($this->rows(self::COLLECTION) as $row) {
            if (is_numeric($row['service_id'] ?? null) && (int) $row['service_id'] === $serviceId) {
                $rows[] = $row;
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $left = CarbonImmutable::parse((string) ($a['created_at'] ?? 'now'), 'UTC');
            $right = CarbonImmutable::parse((string) ($b['created_at'] ?? 'now'), 'UTC');

            return ($right <=> $left) ?: ((int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0));
        });

        $total = count($rows);
        $slice = array_slice($rows, max(0, ($page - 1) * $perPage), $perPage);

        return Page::of(
            array_map(static fn (array $row): ServiceEventRecord => ServiceEventRecord::fromArray($row), $slice),
            $total,
            $perPage,
            $page,
        );
    }

    public function all(): array
    {
        return array_map(
            static fn (array $row): ServiceEventRecord => ServiceEventRecord::fromArray($row),
            $this->rows(self::COLLECTION),
        );
    }

    public function count(): int
    {
        return count($this->rows(self::COLLECTION));
    }
}
