<?php

declare(strict_types=1);

use App\Contracts\ServiceEventRepository;
use App\Contracts\ServiceRepository;
use App\Domain\Data\ServiceQuery;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceEventType;
use App\Domain\Enums\ServiceStatus;

/*
|--------------------------------------------------------------------------
| F-56 — cancelling a service (a feature the old app did not have at all)
|--------------------------------------------------------------------------
*/

it('cancels a service with a reason and writes the audit entry', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)
        ->post('/services/'.$service->id.'/cancel', ['cancellation_reason' => 'Klient zrezygnował z naprawy'])
        ->assertRedirect(route('services.show', $service));

    $fresh = app(ServiceRepository::class)->find($service->id);

    expect($fresh?->status)->toBe(ServiceStatus::Cancelled)
        ->and($fresh?->cancelledBy)->toBe($user->id)
        ->and($fresh?->cancelledAt)->not->toBeNull()
        ->and($fresh?->cancellationReason)->toBe('Klient zrezygnował z naprawy');

    $events = app(ServiceEventRepository::class)->forService($service->id)->items;

    expect($events[0]->type)->toBe(ServiceEventType::Cancelled)
        ->and($events[0]->fromStatus)->toBe('pending')
        ->and($events[0]->toStatus)->toBe('cancelled');
})->with('stores');

it('requires a reason to cancel', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)
        ->post('/services/'.$service->id.'/cancel', ['cancellation_reason' => ''])
        ->assertSessionHasErrors('cancellation_reason');

    expect(app(ServiceRepository::class)->find($service->id)?->status)->toBe(ServiceStatus::Pending);
})->with('stores');

it('removes a cancelled service from the queue but keeps it in the archive', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['device_model' => 'Anulowany laptop']);

    $this->actingAsUser($user)->post('/services/'.$service->id.'/cancel', [
        'cancellation_reason' => 'Nieopłacalna naprawa',
    ]);

    $queue = $this->actingAsUser($user)->get('/');
    $queue->assertOk()->assertDontSee('Anulowany laptop');

    $archived = app(ServiceRepository::class)->paginate(
        ServiceQuery::forStatuses([ServiceStatus::Cancelled], 25)
    );

    expect($archived->total)->toBe(1);
})->with('stores');

it('refuses to cancel a service that was already released', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $repo = app(ServiceRepository::class);
    $service = $repo->save($this->acceptService($user)->with([
        'status' => ServiceStatus::Released->value,
        'released_at' => '2026-11-01 10:00:00',
        'released_by' => $user->id,
    ]));

    $this->actingAsUser($user)
        ->post('/services/'.$service->id.'/cancel', ['cancellation_reason' => 'Za późno'])
        ->assertForbidden();

    expect(app(ServiceRepository::class)->find($service->id)?->status)->toBe(ServiceStatus::Released);
})->with('stores');
