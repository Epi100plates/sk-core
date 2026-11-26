<?php

declare(strict_types=1);

use App\Contracts\ServiceRepository;
use App\Domain\Data\ServiceRecord;
use App\Domain\Enums\Role;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| F-13 — deadline presets (`planned_days`) and an explicit date
|--------------------------------------------------------------------------
|
| The acceptance form offers 1/2/3/7 days, the customer's own number of days or
| a completely different date. The preset is a UI detail: it is converted into a
| wall clock `planned_at` before validation, so actions and stores never see it.
|
*/

/**
 * Frozen "now" for every preset assertion: Thursday 26.11.2026, 10:15 Warsaw.
 */
function freezePresetClock(TestCase $test, string $moment = '2026-11-26 10:15:00'): void
{
    $test->travelTo(CarbonImmutable::parse($moment, 'Europe/Warsaw'));
}

function lastAcceptedService(): ServiceRecord
{
    $service = app(ServiceRepository::class)->all()[0] ?? null;

    expect($service)->toBeInstanceOf(ServiceRecord::class);

    /** @var ServiceRecord $service */
    return $service;
}

it('computes the promised date from a preset or a custom number of days', function (string $store, int $days) {
    usingStore($this, $store);
    freezePresetClock($this);

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->post('/services', [
        'phone' => '505 123 456',
        'device_model' => 'Dell Latitude 5420',
        'issue' => 'Nie ładuje się.',
        'planned_days' => $days,
    ])->assertRedirect();

    $expected = match ($days) {
        1 => '2026-11-27 14:00',
        2 => '2026-11-30 14:00',
        3 => '2026-12-01 14:00',
        7 => '2026-12-07 14:00',
        14 => '2026-12-16 14:00',
    };

    // 15:00 in Warsaw is 14:00 UTC in November — the promise must not shift.
    expect(lastAcceptedService()->plannedAt->utc()->format('Y-m-d H:i'))
        ->toBe($expected);
})->with('stores')->with([1, 2, 3, 7, 14]);

it('accepts a placeholder days value as long as it is absent, not empty', function (string $store) {
    usingStore($this, $store);
    freezePresetClock($this);

    $user = $this->createUser('jan', Role::Employee);

    // The form always posts the field; an empty string means "no preset".
    $this->actingAsUser($user)->post('/services', [
        'phone' => '505 123 456',
        'device_model' => 'Lenovo T490',
        'issue' => 'Bateria do wymiany.',
        'planned_days' => '',
        'planned_at' => '2026-12-01T09:30',
    ])->assertRedirect();

    expect(lastAcceptedService()->plannedAt->utc()->format('Y-m-d H:i'))->toBe('2026-12-01 08:30');
})->with('stores');

it('rejects a number of days outside the allowed range', function (string $store, mixed $days) {
    usingStore($this, $store);
    freezePresetClock($this);

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->post('/services', [
        'phone' => '505 123 456',
        'device_model' => 'Dell Latitude 5420',
        'issue' => 'Nie ładuje się.',
        'planned_days' => $days,
    ])->assertSessionHasErrors('planned_days');

    expect(app(ServiceRepository::class)->count())->toBe(0);
})->with('stores')->with([0, 366, -1, 'abc']);

it('still accepts an explicit planned date without a preset', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->post('/services', [
        'phone' => '505 123 456',
        'device_model' => 'Dell Latitude 5420',
        'issue' => 'Nie ładuje się.',
        'planned_at' => '2026-12-01T09:30',
    ])->assertRedirect();

    expect(lastAcceptedService()->plannedAt->utc()->format('Y-m-d H:i'))->toBe('2026-12-01 08:30');
})->with('stores');

it('prefers the preset over a stale hidden date field', function (string $store) {
    usingStore($this, $store);
    freezePresetClock($this);

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->post('/services', [
        'phone' => '505 123 456',
        'device_model' => 'Dell Latitude 5420',
        'issue' => 'Nie ładuje się.',
        'planned_days' => 2,
        'planned_at' => '2026-12-01T09:30',
    ])->assertRedirect();

    expect(lastAcceptedService()->plannedAt->utc()->format('Y-m-d H:i'))->toBe('2026-11-30 14:00');
})->with('stores');

it('turns a Friday preset of one working day into Monday', function (string $store) {
    usingStore($this, $store);
    freezePresetClock($this, '2026-11-27 10:15:00');

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->post('/services', [
        'phone' => '505 123 456',
        'device_model' => 'Dell Latitude 5420',
        'issue' => 'Nie ładuje się.',
        'planned_days' => 1,
    ])->assertRedirect();

    expect(lastAcceptedService()->plannedAt->utc()->format('Y-m-d H:i'))->toBe('2026-11-30 14:00');
})->with('stores');

it('moves the promised date from the edit form preset', function (string $store) {
    usingStore($this, $store);
    freezePresetClock($this);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)->put('/services/'.$service->id, [
        'planned_days' => 5,
    ])->assertRedirect(route('services.show', $service));

    $fresh = app(ServiceRepository::class)->find($service->id);

    expect($fresh?->plannedAt->utc()->format('Y-m-d H:i'))->toBe('2026-12-03 14:00');
})->with('stores');
