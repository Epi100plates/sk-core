<?php

declare(strict_types=1);
use App\Infrastructure\Json\JsonStore;
use App\Infrastructure\Json\JsonTransaction;
use Illuminate\Contracts\Console\Kernel;

/**
 * Child process helper for the JSON store concurrency test: appends records
 * through the real lock + next_id logic, exactly like two browser requests would.
 *
 * Usage: php json_writer_probe.php <data-dir> <records>
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$path = $argv[1] ?? '';
$records = (int) ($argv[2] ?? 10);

$store = new JsonStore($path, false, true);

for ($i = 0; $i < $records; $i++) {
    $store->withTransaction(static function (JsonTransaction $transaction): void {
        $rows = $transaction->rows('services');
        $rows[] = ['id' => $transaction->nextId('services'), 'phone' => '505123456'];
        $transaction->replace('services', $rows);
    });
}

echo 'done';
