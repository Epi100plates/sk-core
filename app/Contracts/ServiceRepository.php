<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Domain\Data\Page;
use App\Domain\Data\ServiceQuery;
use App\Domain\Data\ServiceRecord;

/**
 * The only way the application reads or writes service orders. Implemented by
 * `Infrastructure\Json` (no database) and `Infrastructure\Eloquent` (MySQL).
 */
interface ServiceRepository
{
    public function find(int $id): ?ServiceRecord;

    public function findByReference(string $reference): ?ServiceRecord;

    /**
     * Creates the record when `id` is 0, updates it otherwise (optimistic
     * versioning: the stored `version` is incremented on every save).
     */
    public function save(ServiceRecord $service): ServiceRecord;

    /**
     * Queue / archive / search in one call.
     *
     * @return Page<ServiceRecord>
     */
    public function paginate(ServiceQuery $query): Page;

    /**
     * Last released services shown at the bottom of the queue (F-21).
     *
     * @return list<ServiceRecord>
     */
    public function recentlyReleased(int $limit = 10): array;

    /**
     * @return array<string, int> status value => count
     */
    public function countsByStatus(): array;

    public function count(): int;

    /**
     * Every record, used by the exporters and the photo verifier.
     *
     * @return list<ServiceRecord>
     */
    public function all(): array;

    /** Next customer facing reference (`SRV-2026-000123`, decision D10). */
    public function nextReference(int $year): string;
}
