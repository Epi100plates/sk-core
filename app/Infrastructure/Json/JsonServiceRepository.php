<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

use App\Contracts\ServiceRepository;
use App\Domain\Data\Page;
use App\Domain\Data\ServiceQuery;
use App\Domain\Data\ServiceRecord;
use App\Domain\Exceptions\DataStoreException;
use App\Domain\Exceptions\VersionConflictException;
use App\Support\Crypto\SecretBox;
use Carbon\CarbonImmutable;

final class JsonServiceRepository implements ServiceRepository
{
    use ReadsJsonCollections;

    private const COLLECTION = 'services';

    public function __construct(
        private readonly JsonStore $store,
        private readonly SecretBox $secrets,
    ) {}

    public function find(int $id): ?ServiceRecord
    {
        foreach ($this->rows(self::COLLECTION) as $row) {
            if (is_numeric($row['id'] ?? null) && (int) $row['id'] === $id) {
                return $this->toRecord($row);
            }
        }

        return null;
    }

    public function findByReference(string $reference): ?ServiceRecord
    {
        $needle = mb_strtolower(trim($reference));

        foreach ($this->rows(self::COLLECTION) as $row) {
            if (mb_strtolower((string) ($row['reference'] ?? '')) === $needle) {
                return $this->toRecord($row);
            }
        }

        return null;
    }

    public function save(ServiceRecord $service): ServiceRecord
    {
        return $this->store->withTransaction(function (JsonTransaction $transaction) use ($service): ServiceRecord {
            $rows = $transaction->rows(self::COLLECTION);
            $now = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');

            if ($service->id === 0) {
                $stored = $service->with([
                    'id' => $transaction->nextId(self::COLLECTION),
                    'version' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $transaction->replace(self::COLLECTION, [...$rows, $this->toRow($stored)]);

                return $stored;
            }

            $existing = null;

            foreach ($rows as $row) {
                if (is_numeric($row['id'] ?? null) && (int) $row['id'] === $service->id) {
                    $existing = $row;

                    break;
                }
            }

            if ($existing === null) {
                throw new DataStoreException('Zlecenie #'.$service->id.' nie istnieje już w magazynie danych.');
            }

            if ((int) ($existing['version'] ?? 0) !== $service->version) {
                throw new VersionConflictException($service->id);
            }

            $stored = $service->with([
                'version' => $service->version + 1,
                'updated_at' => $now,
            ]);

            $result = [];

            foreach ($rows as $row) {
                if (is_numeric($row['id'] ?? null) && (int) $row['id'] === $stored->id) {
                    $result[] = $this->toRow($stored);

                    continue;
                }

                $result[] = $row;
            }

            $transaction->replace(self::COLLECTION, $result);

            return $stored;
        });
    }

    public function paginate(ServiceQuery $query): Page
    {
        $rows = $this->filter($this->rows(self::COLLECTION), $query);
        $rows = $this->sort($rows, $query->sort);
        $total = count($rows);
        $slice = array_slice($rows, $query->offset(), $query->perPage);

        return Page::of(
            array_map(fn (array $row): ServiceRecord => $this->toRecord($row), $slice),
            $total,
            $query->perPage,
            $query->page,
        );
    }

    public function recentlyReleased(int $limit = 10): array
    {
        $rows = array_values(array_filter(
            $this->rows(self::COLLECTION),
            static fn (array $row): bool => ($row['status'] ?? null) === 'released',
        ));

        $rows = $this->sort($rows, ServiceQuery::SORT_RELEASED);

        return array_map(
            fn (array $row): ServiceRecord => $this->toRecord($row),
            array_slice($rows, 0, $limit),
        );
    }

    public function countsByStatus(): array
    {
        $counts = [];

        foreach ($this->rows(self::COLLECTION) as $row) {
            $status = (string) ($row['status'] ?? '');
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }

    public function count(): int
    {
        return count($this->rows(self::COLLECTION));
    }

    public function all(): array
    {
        return array_map(
            fn (array $row): ServiceRecord => $this->toRecord($row),
            $this->rows(self::COLLECTION),
        );
    }

    public function nextReference(int $year): string
    {
        return $this->store->withTransaction(
            static fn (JsonTransaction $transaction): string => $transaction->nextReference($year),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toRecord(array $row): ServiceRecord
    {
        $ciphertext = $row['device_password'] ?? null;
        $row['device_password'] = $this->secrets->decrypt(is_string($ciphertext) ? $ciphertext : null);

        return ServiceRecord::fromArray($row);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(ServiceRecord $service): array
    {
        $row = $service->toArray();
        $row['device_password'] = $this->secrets->encrypt($service->devicePassword);

        return $row;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filter(array $rows, ServiceQuery $query): array
    {
        $statuses = $query->statusValues();
        $term = $query->hasSearchTerm() ? mb_strtolower(trim((string) $query->term)) : null;

        return array_values(array_filter($rows, static function (array $row) use ($statuses, $term, $query): bool {
            if ($statuses !== [] && ! in_array((string) ($row['status'] ?? ''), $statuses, true)) {
                return false;
            }

            if ($query->releasedFrom instanceof CarbonImmutable || $query->releasedTo instanceof CarbonImmutable) {
                $releasedAt = $row['released_at'] ?? null;

                if (! is_string($releasedAt) || $releasedAt === '') {
                    return false;
                }

                $released = CarbonImmutable::parse($releasedAt, 'UTC');

                if ($query->releasedFrom instanceof CarbonImmutable && $released->lt($query->releasedFrom)) {
                    return false;
                }

                if ($query->releasedTo instanceof CarbonImmutable && $released->gt($query->releasedTo)) {
                    return false;
                }
            }

            if ($term !== null && $term !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($row['phone'] ?? ''),
                    (string) ($row['phone_normalized'] ?? ''),
                    (string) ($row['seal_number'] ?? ''),
                    (string) ($row['device_model'] ?? ''),
                    (string) ($row['serial_number'] ?? ''),
                    (string) ($row['issue'] ?? ''),
                    (string) ($row['reference'] ?? ''),
                ]));

                if (! str_contains($haystack, $term)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sort(array $rows, string $mode): array
    {
        usort($rows, static function (array $a, array $b) use ($mode): int {
            if ($mode === ServiceQuery::SORT_RELEASED) {
                return self::compareDates($b['released_at'] ?? null, $a['released_at'] ?? null)
                    ?: self::compareIds($b, $a);
            }

            if ($mode === ServiceQuery::SORT_ACCEPTED) {
                return self::compareDates($b['accepted_at'] ?? null, $a['accepted_at'] ?? null)
                    ?: self::compareIds($b, $a);
            }

            return (self::priority($a) <=> self::priority($b))
                ?: ((int) ($b['is_express'] ?? 0) <=> (int) ($a['is_express'] ?? 0))
                ?: self::compareDates($a['planned_at'] ?? null, $b['planned_at'] ?? null)
                ?: self::compareIds($a, $b);
        });

        return $rows;
    }

    /** @param array<string, mixed> $row */
    private static function priority(array $row): int
    {
        $status = (string) ($row['status'] ?? '');

        return match ($status) {
            'in_progress' => 1,
            'pending' => 2,
            'awaiting_parts' => 3,
            'ready_for_pickup' => 4,
            'released' => 5,
            default => 6,
        };
    }

    private static function compareDates(mixed $a, mixed $b): int
    {
        $left = is_string($a) && $a !== '' ? CarbonImmutable::parse($a, 'UTC') : null;
        $right = is_string($b) && $b !== '' ? CarbonImmutable::parse($b, 'UTC') : null;

        if ($left === null && $right === null) {
            return 0;
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        return $left <=> $right;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function compareIds(array $a, array $b): int
    {
        return (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
    }
}
