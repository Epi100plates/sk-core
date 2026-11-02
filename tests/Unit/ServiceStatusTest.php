<?php

declare(strict_types=1);

use App\Domain\Enums\ServiceStatus;

/*
|--------------------------------------------------------------------------
| Domain enums
|--------------------------------------------------------------------------
*/

it('maps legacy statuses to the new ones', function (string $legacy, ServiceStatus $expected) {
    expect(ServiceStatus::fromLegacy($legacy))->toBe($expected);
})->with([
    ['oczekujacy', ServiceStatus::Pending],
    ['w_trakcie', ServiceStatus::InProgress],
    ['czeka_na_czesci', ServiceStatus::AwaitingParts],
    ['do_wydania', ServiceStatus::ReadyForPickup],
    ['wydany', ServiceStatus::Released],
    ['anulowane', ServiceStatus::Cancelled],
    ['cokolwiek', ServiceStatus::Pending],
]);

it('only allows transitions defined by the workflow', function () {
    expect(ServiceStatus::Pending->canTransitionTo(ServiceStatus::InProgress))->toBeTrue()
        ->and(ServiceStatus::InProgress->canTransitionTo(ServiceStatus::ReadyForPickup))->toBeTrue()
        ->and(ServiceStatus::AwaitingParts->canTransitionTo(ServiceStatus::InProgress))->toBeTrue()
        ->and(ServiceStatus::ReadyForPickup->canTransitionTo(ServiceStatus::Released))->toBeTrue()
        ->and(ServiceStatus::Released->canTransitionTo(ServiceStatus::Pending))->toBeFalse()
        ->and(ServiceStatus::Released->canTransitionTo(ServiceStatus::Released))->toBeFalse()
        ->and(ServiceStatus::Cancelled->canTransitionTo(ServiceStatus::InProgress))->toBeFalse();
});

it('has no next stage for released and cancelled services', function () {
    expect(ServiceStatus::Pending->nextStatus())->toBe(ServiceStatus::InProgress)
        ->and(ServiceStatus::InProgress->nextStatus())->toBe(ServiceStatus::ReadyForPickup)
        ->and(ServiceStatus::AwaitingParts->nextStatus())->toBe(ServiceStatus::InProgress)
        ->and(ServiceStatus::ReadyForPickup->nextStatus())->toBeNull()
        ->and(ServiceStatus::Released->nextStatus())->toBeNull()
        ->and(ServiceStatus::Cancelled->nextStatus())->toBeNull();
});

it('sorts final statuses last in the queue', function () {
    expect(ServiceStatus::InProgress->queuePriority())->toBeLessThan(ServiceStatus::Pending->queuePriority())
        ->and(ServiceStatus::Pending->queuePriority())->toBeLessThan(ServiceStatus::AwaitingParts->queuePriority())
        ->and(ServiceStatus::AwaitingParts->queuePriority())->toBeLessThan(ServiceStatus::ReadyForPickup->queuePriority())
        ->and(ServiceStatus::ReadyForPickup->queuePriority())->toBeLessThan(ServiceStatus::Released->queuePriority())
        ->and(ServiceStatus::Released->queuePriority())->toBeLessThan(ServiceStatus::Cancelled->queuePriority());
});

it('never offers released or cancelled in the edit form', function () {
    $options = array_keys(ServiceStatus::editableOptions());

    expect($options)->not->toContain('released')
        ->and($options)->not->toContain('cancelled')
        ->and($options)->toContain('pending');
});
