<?php

declare(strict_types=1);

use App\Domain\FieldLabels;
use App\Domain\ServiceDiff;
use Carbon\CarbonImmutable;

/*
|--------------------------------------------------------------------------
| Formatting and audit diff (defect D-57: raw column names and ISO dates)
|--------------------------------------------------------------------------
*/

it('formats dates in the local timezone, not ISO', function () {
    config(['app.display_timezone' => 'Europe/Warsaw']);

    // 15:00 UTC in summer is 17:00 in Warsaw.
    $formatted = FieldLabels::formatDate('2026-07-01 15:00:00');

    expect($formatted)->toBe('01.07.2026 17:00');
});

it('formats winter dates with the winter offset', function () {
    config(['app.display_timezone' => 'Europe/Warsaw']);

    expect(FieldLabels::formatDate(CarbonImmutable::parse('2026-01-15 15:00:00', 'UTC')))
        ->toBe('15.01.2026 16:00');
});

it('formats a day header without a misleading time', function () {
    config(['app.display_timezone' => 'Europe/Warsaw']);

    // Midnight UTC is 01:00 in Warsaw; a day group must not show that as the release time.
    expect(FieldLabels::formatDay('2026-11-02 00:00:00'))->toBe('02.11.2026');
});

it('translates field names for the history panel', function () {
    expect(FieldLabels::label('planned_at'))->toBe('Termin realizacji')
        ->and(FieldLabels::label('cost'))->toBe('Koszt części')
        ->and(FieldLabels::label('unknown_field'))->toBe('unknown_field');
});

it('formats money and booleans in the diff', function () {
    expect(FieldLabels::format('price', '199.99'))->toBe('199,99 zł')
        ->and(FieldLabels::format('is_express', true))->toBe('Tak')
        ->and(FieldLabels::format('status', 'ready_for_pickup'))->toBe('Do wydania')
        ->and(FieldLabels::format('notes', null))->toBe('—');
});

it('formats a phone number the way the customer wrote it', function () {
    expect(FieldLabels::format('phone', '+48500102877'))->toBe('+48 500 102 877')
        ->and(FieldLabels::format('phone', '505 123 456'))->toBe('+48 505 123 456');
});

it('detects only the fields that really changed', function () {
    $before = ['price' => '100.00', 'cost' => '50.00', 'planned_at' => '2026-11-05 15:00:00', 'is_express' => false];
    $after = $before;
    $after['cost'] = '60.00';

    $diff = ServiceDiff::between($before, $after);

    expect(array_keys($diff))->toBe(['cost'])
        ->and($diff['cost']['from'])->toBe('50.00')
        ->and($diff['cost']['to'])->toBe('60.00');
});

it('treats an identical snapshot as no change (defect D-44)', function () {
    $snapshot = ['planned_at' => '2026-11-05 15:00:00', 'price' => '199.99', 'is_express' => true];

    expect(ServiceDiff::between($snapshot, $snapshot))->toBe([]);
});

it('declines Polish plurals like a native speaker', function (int $count, string $expected) {
    expect(FieldLabels::plural($count, 'zlecenie', 'zlecenia', 'zleceń'))->toBe($expected);
})->with([
    [0, '0 zleceń'],
    [1, '1 zlecenie'],
    [3, '3 zlecenia'],
    [12, '12 zleceń'],
    [22, '22 zlecenia'],
    [25, '25 zleceń'],
]);

it('hides audit plumbing from the history panel', function () {
    expect(FieldLabels::hiddenInHistory('status'))->toBeTrue()
        ->and(FieldLabels::hiddenInHistory('released_by'))->toBeTrue()
        ->and(FieldLabels::hiddenInHistory('cancellation_reason'))->toBeTrue()
        ->and(FieldLabels::hiddenInHistory('phone'))->toBeFalse()
        ->and(FieldLabels::hiddenInHistory('planned_at'))->toBeFalse();
});

it('masks the device password in the history', function () {
    expect(FieldLabels::format('device_password', 'tajne-haslo'))->toBe('••••')
        ->and(FieldLabels::format('device_password', null))->toBe('—');
});

it('stores a marker instead of the device password in the diff', function () {
    $before = ['device_password' => 'stare-haslo'];
    $after = ['device_password' => 'nowe-haslo'];

    $diff = ServiceDiff::between($before, $after);

    expect($diff)->toHaveKey('device_password')
        ->and($diff['device_password']['from'])->toBe('••••')
        ->and($diff['device_password']['to'])->toBe('••••')
        ->and(json_encode($diff))->not->toContain('nowe-haslo');

    $snapshot = ServiceDiff::snapshot(['device_password' => 'tajne-haslo', 'phone' => '505123456']);

    expect(json_encode($snapshot))->not->toContain('tajne-haslo');
});
