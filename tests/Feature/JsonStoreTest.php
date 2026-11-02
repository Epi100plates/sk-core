<?php

declare(strict_types=1);

use App\Contracts\ServiceRepository;
use App\Contracts\TransactionManager;
use App\Domain\Data\ServiceRecord;
use App\Domain\Enums\Role;
use App\Domain\Exceptions\DataStoreCorruptedException;
use App\Domain\Exceptions\VersionConflictException;
use App\Infrastructure\Json\JsonStore;
use Symfony\Component\Process\Process;

/*
|--------------------------------------------------------------------------
| JSON store guarantees (plan 5.7, tasks T2.3): atomic writes, locking,
| schema versioning and a controlled failure instead of silent data loss
|--------------------------------------------------------------------------
*/

function jsonStore(): JsonStore
{
    return app(JsonStore::class);
}

it('initialises the data files with a schema version', function () {
    $this->useStore('json');

    $store = jsonStore();

    expect($store->isInitialized())->toBeTrue()
        ->and($store->readMeta()['schema_version'])->toBe(JsonStore::SCHEMA_VERSION);

    foreach (JsonStore::COLLECTIONS as $collection) {
        expect(is_file($store->file($collection)))->toBeTrue();
    }
});

it('writes and reads a service through the repository', function () {
    $this->useStore('json');

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['device_password' => 'tajne']);

    $stored = app(ServiceRepository::class)->find($service->id);

    expect($stored?->reference)->toBe($service->reference)
        ->and($stored?->devicePassword)->toBe('tajne');

    // The device password must not sit in the file as plain text (defect D-09).
    $raw = (string) file_get_contents(jsonStore()->file('services'));
    expect($raw)->not->toContain('"tajne"');
});

it('increments next_id inside the lock', function () {
    $this->useStore('json');

    $store = jsonStore();

    $ids = [];

    for ($i = 0; $i < 5; $i++) {
        $ids[] = $store->withTransaction(
            static fn ($tx): int => $tx->nextId('services')
        );
    }

    expect($ids)->toBe([1, 2, 3, 4, 5]);
});

it('keeps a .bak copy and refuses to continue when the file is corrupted', function () {
    $this->useStore('json');

    $store = jsonStore();
    file_put_contents($store->file('services'), '{"schema_version":1,"rows":[{"id":1,');

    expect(fn () => app(ServiceRepository::class)->count())
        ->toThrow(DataStoreCorruptedException::class);

    $backups = glob($store->file('services').'.corrupt*.bak');
    expect($backups)->not->toBeEmpty();
});

it('keeps a .bak copy and refuses an unknown schema version', function () {
    $this->useStore('json');

    $store = jsonStore();
    file_put_contents($store->file('services'), json_encode(['schema_version' => 99, 'rows' => []]));

    expect(fn () => app(ServiceRepository::class)->count())
        ->toThrow(DataStoreCorruptedException::class);

    expect(glob($store->file('services').'.schema-99*.bak'))->not->toBeEmpty();
});

it('rolls the whole unit of work back when it fails', function () {
    $this->useStore('json');

    $user = $this->createUser('jan', Role::Employee);
    $repo = app(ServiceRepository::class);
    $transactions = app(TransactionManager::class);

    expect($repo->count())->toBe(0);

    try {
        $transactions->run(function () use ($user, $repo): void {
            $repo->save(ServiceRecord::fromArray([
                'id' => 0,
                'reference' => 'SRV-2026-000001',
                'phone' => '505 123 456',
                'device_model' => 'Test',
                'issue' => 'Test',
                'planned_at' => '2026-11-05 15:00:00',
                'status' => 'pending',
                'accepted_by' => $user->id,
                'accepted_at' => '2026-11-02 10:00:00',
            ]));

            throw new RuntimeException('awaria w trakcie zapisu');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($repo->count())->toBe(0);
});

it('rejects a stale write with a version conflict', function () {
    $this->useStore('json');

    $user = $this->createUser('jan', Role::Employee);
    $repo = app(ServiceRepository::class);
    $service = $this->acceptService($user);

    $stale = $repo->find($service->id);
    $repo->save($service->with(['notes' => 'pierwsza zmiana']));

    expect(fn () => $repo->save($stale->with(['notes' => 'druga zmiana'])))
        ->toThrow(VersionConflictException::class);
});

it('survives two processes writing at the same time', function () {
    $this->useStore('json');

    $store = jsonStore();
    $probe = base_path('tests/Fixtures/json_writer_probe.php');
    $records = 25;

    $first = new Process([PHP_BINARY, $probe, $store->path(), (string) $records]);
    $second = new Process([PHP_BINARY, $probe, $store->path(), (string) $records]);

    $first->start();
    $second->start();

    while ($first->isRunning() || $second->isRunning()) {
        usleep(20_000);
    }

    expect($first->getExitCode())->toBe(0, (string) $first->getErrorOutput())
        ->and($second->getExitCode())->toBe(0, (string) $second->getErrorOutput());

    $rows = $store->readCollectionFromDisk('services');

    expect($rows)->toHaveCount($records * 2);

    $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
    sort($ids);

    expect($ids)->toBe(range(1, $records * 2));
});
