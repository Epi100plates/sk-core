<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

use App\Contracts\ServicePhotoRepository;
use App\Domain\Data\ServicePhotoRecord;
use App\Domain\Exceptions\DataStoreException;
use Carbon\CarbonImmutable;

final class JsonServicePhotoRepository implements ServicePhotoRepository
{
    use ReadsJsonCollections;

    private const COLLECTION = 'service_photos';

    public function __construct(private readonly JsonStore $store) {}

    public function find(int $id): ?ServicePhotoRecord
    {
        foreach ($this->rows(self::COLLECTION) as $row) {
            if (is_numeric($row['id'] ?? null) && (int) $row['id'] === $id) {
                return ServicePhotoRecord::fromArray($row);
            }
        }

        return null;
    }

    public function save(ServicePhotoRecord $photo): ServicePhotoRecord
    {
        return $this->store->withTransaction(function (JsonTransaction $transaction) use ($photo): ServicePhotoRecord {
            $rows = $transaction->rows(self::COLLECTION);

            if ($photo->id === 0) {
                $stored = $photo->withId($transaction->nextId(self::COLLECTION));
                $stored = $stored->with(['created_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s')]);
                $transaction->replace(self::COLLECTION, [...$rows, $stored->toArray()]);

                return $stored;
            }

            $result = [];
            $replaced = false;

            foreach ($rows as $row) {
                if (is_numeric($row['id'] ?? null) && (int) $row['id'] === $photo->id) {
                    $result[] = $photo->toArray();
                    $replaced = true;

                    continue;
                }

                $result[] = $row;
            }

            if (! $replaced) {
                throw new DataStoreException('Zdjęcie #'.$photo->id.' nie istnieje już w magazynie danych.');
            }

            $transaction->replace(self::COLLECTION, $result);

            return $photo;
        });
    }

    public function forService(int $serviceId): array
    {
        $records = [];

        foreach ($this->rows(self::COLLECTION) as $row) {
            if (is_numeric($row['service_id'] ?? null) && (int) $row['service_id'] === $serviceId) {
                $records[] = ServicePhotoRecord::fromArray($row);
            }
        }

        usort($records, static fn (ServicePhotoRecord $a, ServicePhotoRecord $b): int => $a->id <=> $b->id);

        return $records;
    }

    public function delete(int $id): void
    {
        $this->store->withTransaction(static function (JsonTransaction $transaction) use ($id): void {
            $transaction->delete(self::COLLECTION, $id);
        });
    }

    public function all(): array
    {
        return array_map(
            static fn (array $row): ServicePhotoRecord => ServicePhotoRecord::fromArray($row),
            $this->rows(self::COLLECTION),
        );
    }

    public function count(): int
    {
        return count($this->rows(self::COLLECTION));
    }
}
