<?php

declare(strict_types=1);

use App\Domain\BusinessDays;
use Carbon\CarbonImmutable;

/*
|--------------------------------------------------------------------------
| Working-day promises (F-13): Friday + 1 day is Monday, never Saturday
|--------------------------------------------------------------------------
*/

it('counts only Monday to Friday when adding a deadline', function (string $start, int $days, string $expected) {
    $date = BusinessDays::add(CarbonImmutable::parse($start, 'Europe/Warsaw'), $days);

    expect($date->toDateString())->toBe($expected)
        ->and($date->isWeekday())->toBeTrue();
})->with([
    'Friday + 1' => ['2026-11-27 10:15:00', 1, '2026-11-30'],
    'Saturday + 1' => ['2026-11-28 10:15:00', 1, '2026-11-30'],
    'Sunday + 1' => ['2026-11-29 10:15:00', 1, '2026-11-30'],
    'Thursday + 2' => ['2026-11-26 10:15:00', 2, '2026-11-30'],
    'Monday + 5' => ['2026-11-30 10:15:00', 5, '2026-12-07'],
    'Friday + 7' => ['2026-11-27 10:15:00', 7, '2026-12-08'],
    'Friday + 14' => ['2026-11-27 10:15:00', 14, '2026-12-17'],
]);

it('does not carry the time of day into the counted date', function () {
    $date = BusinessDays::add(CarbonImmutable::parse('2026-11-27 16:45:00', 'Europe/Warsaw'), 1);

    expect($date->format('Y-m-d H:i'))->toBe('2026-11-30 00:00');
});
