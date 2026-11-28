<?php

declare(strict_types=1);

use App\Contracts\ServiceEventRepository;
use App\Contracts\ServiceRepository;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceEventType;
use App\Domain\Enums\ServiceStatus;
use Carbon\CarbonImmutable;

/*
|--------------------------------------------------------------------------
| F-24, F-33, F-39…F-42 — status workflow and releasing (D-10, D-11, D-46)
|--------------------------------------------------------------------------
*/

it('advances a service through the workflow stages', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)->post('/services/'.$service->id.'/advance')->assertRedirect();

    expect(app(ServiceRepository::class)->find($service->id)?->status)->toBe(ServiceStatus::InProgress);

    // "Gotowy do wydania": the technician asks the owner to accept the release.
    $this->actingAsUser($user)->post('/services/'.$service->id.'/advance')->assertRedirect();

    expect(app(ServiceRepository::class)->find($service->id)?->status)->toBe(ServiceStatus::AwaitingApproval);

    // The acceptance has its own action; the generic next stage is exhausted.
    $this->actingAsUser($user)->post('/services/'.$service->id.'/advance')->assertStatus(422);

    $events = app(ServiceEventRepository::class)->forService($service->id)->items;
    $statusEvents = array_values(array_filter(
        $events,
        static fn ($event): bool => in_array($event->type, [ServiceEventType::StatusChanged, ServiceEventType::ReleaseRequested], true),
    ));

    expect($statusEvents)->toHaveCount(2);
})->with('stores');

it('answers 409-free 422 for a service that has no next stage', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $repo = app(ServiceRepository::class);
    $service = $repo->save($this->acceptService($user)->with(['status' => ServiceStatus::ReadyForPickup->value]));

    $this->actingAsUser($user)->post('/services/'.$service->id.'/advance')->assertStatus(422);
})->with('stores');

it('returns the new status as JSON for an AJAX request', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)
        ->postJson('/services/'.$service->id.'/advance')
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');
})->with('stores');

it('releases a laptop with the date from the form and the current employee', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['price' => '100.00', 'cost' => '30.00']);
    $service = app(ServiceRepository::class)->save($service->with(['status' => ServiceStatus::ReadyForPickup->value]));

    $response = $this->actingAsUser($user)->post('/services/'.$service->id.'/release', [
        'released_at' => '2026-11-01T12:30',
        'price' => '249,99',
        'cost' => '99,50',
        'notes' => 'Wydane synowi klienta.',
        'seal_number' => 'PLO-777',
        'device_password' => 'nowe-haslo',
        'serial_number' => 'SN-9',
    ]);

    $response->assertRedirect(route('services.show', $service));

    $fresh = app(ServiceRepository::class)->find($service->id);

    expect($fresh?->status)->toBe(ServiceStatus::Released)
        ->and($fresh?->releasedBy)->toBe($user->id)
        ->and($fresh?->price)->toBe('249.99')
        ->and($fresh?->cost)->toBe('99.50')
        ->and($fresh?->notes)->toBe('Wydane synowi klienta.')
        ->and($fresh?->sealNumber)->toBe('PLO-777')
        ->and($fresh?->devicePassword)->toBe('nowe-haslo')
        ->and($fresh?->serialNumber)->toBe('SN-9')
        // 12:30 Warsaw time on 2026-11-01 is 11:30 UTC.
        ->and($fresh?->releasedAt?->utc()->format('Y-m-d H:i'))->toBe('2026-11-01 11:30');

    $events = app(ServiceEventRepository::class)->forService($service->id)->items;

    expect($events[0]->type)->toBe(ServiceEventType::Released)
        ->and($events[0]->fromStatus)->toBe('ready_for_pickup')
        ->and($events[0]->toStatus)->toBe('released');
})->with('stores');

it('allows releasing from a phone user agent (regression D-10)', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);
    $service = app(ServiceRepository::class)->save($service->with(['status' => ServiceStatus::ReadyForPickup->value]));

    $this->actingAsUser($user)
        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148'])
        ->post('/services/'.$service->id.'/release', [
            'released_at' => '2026-11-01T12:30',
            'price' => '100',
            'cost' => '30',
        ])
        ->assertRedirect();

    expect(app(ServiceRepository::class)->find($service->id)?->status)->toBe(ServiceStatus::Released);
})->with('stores');

it('rejects a release date from the future', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);
    $service = app(ServiceRepository::class)->save($service->with(['status' => ServiceStatus::ReadyForPickup->value]));

    $future = CarbonImmutable::now('Europe/Warsaw')->addDays(3)->format('Y-m-d\TH:i');

    $this->actingAsUser($user)
        ->post('/services/'.$service->id.'/release', [
            'released_at' => $future,
            'price' => '100',
            'cost' => '30',
        ])
        ->assertSessionHasErrors('released_at');

    expect(app(ServiceRepository::class)->find($service->id)?->status)->toBe(ServiceStatus::ReadyForPickup);
})->with('stores');

it('refuses to release the same laptop twice', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);
    $service = app(ServiceRepository::class)->save($service->with(['status' => ServiceStatus::ReadyForPickup->value]));

    $payload = ['released_at' => '2026-11-01T12:30', 'price' => '100', 'cost' => '30'];

    $this->actingAsUser($user)->post('/services/'.$service->id.'/release', $payload)->assertRedirect();
    $this->actingAsUser($user)->post('/services/'.$service->id.'/release', $payload)->assertStatus(422);
})->with('stores');

it('refuses to release a laptop that still waits for the owner acceptance', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);
    $service = app(ServiceRepository::class)->save($service->with(['status' => ServiceStatus::AwaitingApproval->value]));

    // The workflow rule lives in the action, not only in the hidden button.
    $this->actingAsUser($user)
        ->post('/services/'.$service->id.'/release', [
            'released_at' => '2026-11-01T12:30',
            'price' => '100',
            'cost' => '30',
        ])
        ->assertStatus(422);

    expect(app(ServiceRepository::class)->find($service->id)?->status)->toBe(ServiceStatus::AwaitingApproval);
})->with('stores');

it('renders the release form with the service data and a "now" default', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['price' => '199.99', 'cost' => '50.00']);
    $service = app(ServiceRepository::class)->save($service->with(['status' => ServiceStatus::ReadyForPickup->value]));

    $this->actingAsUser($user)->get('/services/'.$service->id.'/release')
        ->assertOk()
        ->assertSee('Protokół wydania')
        ->assertSee('199,99')
        ->assertSee('50,00');
})->with('stores');

it('prints the release protocol after releasing when asked', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);
    $service = app(ServiceRepository::class)->save($service->with(['status' => ServiceStatus::ReadyForPickup->value]));

    $this->actingAsUser($user)
        ->post('/services/'.$service->id.'/release', [
            'released_at' => '2026-11-01T12:30',
            'price' => '100',
            'cost' => '30',
            'print_protocol' => '1',
        ])
        ->assertRedirect(route('print.release', app(ServiceRepository::class)->find($service->id)));
})->with('stores');
