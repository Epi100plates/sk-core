<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

use Carbon\CarbonImmutable;

/**
 * A single locked read-modify-write cycle over several collections. Changes are
 * kept in memory and only written when the whole callback succeeded, which is
 * the JSON equivalent of a database transaction.
 */
final class JsonTransaction
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $rows = [];

    /** @var array<string, bool> */
    private array $loaded = [];

    /** @var array<string, bool> */
    private array $dirty = [];

    /** @var array<string, mixed> */
    private array $meta;

    private bool $metaDirty = false;

    public function __construct(private readonly JsonStore $store)
    {
        $this->meta = $store->readMeta();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(string $collection): array
    {
        if (! ($this->loaded[$collection] ?? false)) {
            $payload = $this->store->readCollectionFromDisk($collection);
            $this->rows[$collection] = $payload;
            $this->loaded[$collection] = true;
        }

        return $this->rows[$collection] ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function replace(string $collection, array $rows): void
    {
        $this->rows[$collection] = array_values($rows);
        $this->loaded[$collection] = true;
        $this->dirty[$collection] = true;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed> the stored row (with a generated id)
     */
    public function upsert(string $collection, array $row): array
    {
        $rows = $this->rows($collection);
        $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;

        if ($id === 0) {
            $row['id'] = $this->nextId($collection);
            $rows[] = $row;
            $this->replace($collection, $rows);

            return $row;
        }

        $replaced = false;

        foreach ($rows as $index => $existing) {
            if (is_numeric($existing['id'] ?? null) && (int) $existing['id'] === $id) {
                $rows[$index] = $row;
                $replaced = true;

                break;
            }
        }

        if (! $replaced) {
            $rows[] = $row;
        }

        $this->replace($collection, $rows);

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $collection, int $id): ?array
    {
        foreach ($this->rows($collection) as $row) {
            if (is_numeric($row['id'] ?? null) && (int) $row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    public function delete(string $collection, int $id): bool
    {
        $rows = $this->rows($collection);
        $kept = [];
        $removed = false;

        foreach ($rows as $row) {
            if (is_numeric($row['id'] ?? null) && (int) $row['id'] === $id) {
                $removed = true;

                continue;
            }

            $kept[] = $row;
        }

        if ($removed) {
            $this->replace($collection, $kept);
        }

        return $removed;
    }

    public function nextId(string $collection): int
    {
        $nextIds = $this->meta['next_ids'] ?? [];

        if (! is_array($nextIds)) {
            $nextIds = [];
        }

        $current = isset($nextIds[$collection]) && is_numeric($nextIds[$collection])
            ? (int) $nextIds[$collection]
            : 1;

        $nextIds[$collection] = $current + 1;
        $this->meta['next_ids'] = $nextIds;
        $this->metaDirty = true;

        return $current;
    }

    public function nextReference(int $year): string
    {
        $counterYear = isset($this->meta['reference_year']) && is_numeric($this->meta['reference_year'])
            ? (int) $this->meta['reference_year']
            : $year;

        $counter = isset($this->meta['reference_counter']) && is_numeric($this->meta['reference_counter'])
            ? (int) $this->meta['reference_counter']
            : 1;

        if ($counterYear !== $year) {
            $counterYear = $year;
            $counter = 1;
        }

        $this->meta['reference_year'] = $counterYear;
        $this->meta['reference_counter'] = $counter + 1;
        $this->metaDirty = true;

        return sprintf('SRV-%d-%06d', $year, $counter);
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    public function flush(): void
    {
        foreach (array_keys($this->dirty) as $collection) {
            $this->store->writeJson($this->store->file($collection), $this->store->payload($this->rows[$collection] ?? []));
        }

        if ($this->metaDirty || $this->dirty !== []) {
            $this->meta['last_write'] = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');
            $this->store->writeJson($this->store->metaFile(), $this->meta);
        }

        $this->dirty = [];
        $this->metaDirty = false;
    }
}
