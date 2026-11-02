<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Runs a unit of work atomically no matter which store is active: one `flock`
 * protected read-modify-write cycle for JSON, `DB::transaction()` for MySQL.
 * Actions wrap their whole change (service + audit event + photos) in this, so
 * a half written service can never exist (defect D-17).
 */
interface TransactionManager
{
    /**
     * @param  callable(): mixed  $callback
     */
    public function run(callable $callback): mixed;
}
