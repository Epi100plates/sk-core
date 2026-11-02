<?php

declare(strict_types=1);

use Tests\DuskTestCase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest configuration
|--------------------------------------------------------------------------
|
| Every feature test that writes data is executed twice — once on the JSON store
| and once on MySQL — via the `stores` dataset. `usingStore()` switches the
| active driver and re-binds the repositories.
|
| The Dusk browser suite lives in tests/Browser and has its own base class.
|
*/

uses(TestCase::class)->in('Feature', 'Unit');
uses(DuskTestCase::class)->in('Browser');

dataset('stores', ['json', 'mysql']);

/**
 * Prepares the requested data store for the current test and skips the MySQL
 * run when no database is reachable on this machine.
 */
function usingStore(TestCase $test, string $store): void
{
    if ($store === 'mysql' && ! $test->mysqlAvailable()) {
        $test->markTestSkipped('MySQL nie jest dostępny — uruchom MariaDB, aby wykonać ten wariant.');
    }

    $test->useStore($store);
}
