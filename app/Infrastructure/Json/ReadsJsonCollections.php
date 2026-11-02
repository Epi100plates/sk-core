<?php

declare(strict_types=1);

namespace App\Infrastructure\Json;

/**
 * Shared access to collection rows that is aware of an open transaction: inside
 * a unit of work we must see the not-yet-flushed rows, outside we read files.
 */
trait ReadsJsonCollections
{
    /**
     * @return list<array<string, mixed>>
     */
    protected function rows(string $collection): array
    {
        $active = $this->store->activeTransaction();

        if ($active instanceof JsonTransaction) {
            return $active->rows($collection);
        }

        return $this->store->readCollection($collection);
    }
}
