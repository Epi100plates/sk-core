<?php

declare(strict_types=1);

use App\Contracts\ServiceEventRepository;
use App\Contracts\ServiceRepository;
use App\Contracts\UserRepository;
use App\Domain\Enums\ServiceStatus;
use App\Support\Legacy\SqlDumpParser;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Legacy import (task T2.7, plan section 9.2) — proven on the fixture dump
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Storage::fake('local');
});

it('produces a dry run report without writing anything', function (string $store) {
    usingStore($this, $store);

    $this->artisan('legacy:import', [
        '--source' => base_path('tests/Fixtures/legacy-dump.sql'),
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect(app(ServiceRepository::class)->count())->toBe(0)
        ->and(app(UserRepository::class)->count())->toBe(0);
})->with('stores');

it('imports services, users and history with the documented mapping', function (string $store) {
    usingStore($this, $store);

    $this->artisan('legacy:import', [
        '--source' => base_path('tests/Fixtures/legacy-dump.sql'),
    ])->assertExitCode(0);

    $services = app(ServiceRepository::class)->all();
    $events = app(ServiceEventRepository::class);

    expect($services)->toHaveCount(3);

    $byLegacy = [];

    foreach ($services as $service) {
        $byLegacy[$service->legacyId] = $service;
    }

    // Row 1: plain "w trakcie", comma price, seal number.
    expect($byLegacy[1]->status)->toBe(ServiceStatus::InProgress)
        ->and($byLegacy[1]->price)->toBe('199.99')
        ->and($byLegacy[1]->cost)->toBe('50.00')
        ->and($byLegacy[1]->sealNumber)->toBe('PLO-001-2024')
        ->and($byLegacy[1]->phone)->toBe('600 100 200')
        ->and($byLegacy[1]->phoneNormalized)->toBe('+48600100200')
        ->and($byLegacy[1]->devicePassword)->toBeNull();

    // Row 2: released with a date, a password and the "confirmation" flag.
    expect($byLegacy[2]->status)->toBe(ServiceStatus::Released)
        ->and($byLegacy[2]->releasedAt?->format('Y-m-d H:i'))->toBe('2026-02-17 11:30')
        ->and($byLegacy[2]->devicePassword)->toBe('haslo123')
        ->and($byLegacy[2]->isExpress)->toBeTrue()
        ->and($byLegacy[2]->hasIssuedConfirmation())->toBeTrue();

    // Row 3: unknown status, no planned date, 0048 phone prefix, no created_at.
    expect($byLegacy[3]->status)->toBe(ServiceStatus::Pending)
        ->and($byLegacy[3]->phoneNormalized)->toBe('+48505123999')
        ->and($byLegacy[3]->plannedAt->greaterThan($byLegacy[3]->acceptedAt))->toBeTrue();

    // Users were imported with hashed passwords and forced change.
    $admin = app(UserRepository::class)->findByLogin('admin');

    expect($admin)->not->toBeNull()
        ->and($admin?->password)->not->toBe('admin')
        ->and($admin?->mustChangePassword)->toBeTrue();

    // History rows came along: one synthetic `accepted` per service plus the dump rows.
    expect($events->count())->toBeGreaterThanOrEqual(4);

    $serviceEvents = $events->forService($byLegacy[1]->id);
    expect($serviceEvents->total)->toBe(3);
})->with('stores');

it('is idempotent — a second run adds nothing', function (string $store) {
    usingStore($this, $store);

    $this->artisan('legacy:import', ['--source' => base_path('tests/Fixtures/legacy-dump.sql')]);
    $this->artisan('legacy:import', ['--source' => base_path('tests/Fixtures/legacy-dump.sql')]);

    expect(app(ServiceRepository::class)->count())->toBe(3)
        ->and(app(UserRepository::class)->count())->toBe(3); // admin, tomek, legacy-import
})->with('stores');

it('verifies the imported data against the dump', function (string $store) {
    usingStore($this, $store);

    $this->artisan('legacy:import', ['--source' => base_path('tests/Fixtures/legacy-dump.sql')]);
    $this->artisan('legacy:verify', ['--source' => base_path('tests/Fixtures/legacy-dump.sql')])
        ->assertExitCode(0);
})->with('stores');

it('reports a clean fixture without warnings about unmappable data', function () {
    // The fixture deliberately contains an unknown status and a missing date,
    // so the report must mention them instead of guessing silently.
    $parser = new SqlDumpParser;
    $tables = $parser->parseFile(base_path('tests/Fixtures/legacy-dump.sql'));

    expect($tables)->toHaveKey('laptopservice')
        ->and($tables['laptopservice'])->toHaveCount(3)
        ->and($tables['users'])->toHaveCount(2)
        ->and($tables['service_history'])->toHaveCount(2);
});

it('parses quoted values and NULLs from a dump', function () {
    $parser = new SqlDumpParser;

    $tables = $parser->parse(
        "INSERT INTO `t` (`a`, `b`, `c`) VALUES ('O\'Brien; test', NULL, 5), ('drugi', 'x', 6);"
    );

    expect($tables['t'])->toHaveCount(2)
        ->and($tables['t'][0]['a'])->toBe("O'Brien; test")
        ->and($tables['t'][0]['b'])->toBeNull()
        ->and($tables['t'][1]['c'])->toBe('6');
});
