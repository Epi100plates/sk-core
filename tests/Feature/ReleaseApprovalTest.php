<?php

declare(strict_types=1);

use App\Contracts\ServiceEventRepository;
use App\Contracts\ServiceRepository;
use App\Domain\Enums\Ability;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceEventType;
use App\Domain\Enums\ServiceStatus;

/*
|--------------------------------------------------------------------------
| Release approval flow (owner request 2026-11-27)
|
| request (in_progress -> awaiting_approval) -> owner confirms (ready_for_pickup)
| -> release as before. The owner can return to the queue with remarks
| (with_remarks), the technician resumes (in_progress) and asks again.
|--------------------------------------------------------------------------
*/

it('lets the technician request the owner acceptance from the repair stage', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptServiceWithStatus($user, ServiceStatus::InProgress);

    $this->actingAsUser($user)->post('/services/'.$service->id.'/advance')->assertRedirect();

    expect(app(ServiceRepository::class)->find($service->id)?->status)->toBe(ServiceStatus::AwaitingApproval);

    $events = app(ServiceEventRepository::class)->forService($service->id)->items;
    $requested = array_values(array_filter(
        $events,
        static fn ($event): bool => $event->type === ServiceEventType::ReleaseRequested,
    ));

    expect($requested)->toHaveCount(1)
        ->and($requested[0]->fromStatus)->toBe('in_progress')
        ->and($requested[0]->toStatus)->toBe('awaiting_approval');
})->with('stores');

it('lets the owner confirm the release request onto the release counter', function (string $store) {
    usingStore($this, $store);

    $owner = $this->createUser('szef', Role::Owner);
    $service = $this->acceptServiceWithStatus($owner, ServiceStatus::AwaitingApproval);

    $this->actingAsUser($owner)
        ->post('/services/'.$service->id.'/release-approval')
        ->assertRedirect(route('services.show', $service));

    $fresh = app(ServiceRepository::class)->find($service->id);

    expect($fresh?->status)->toBe(ServiceStatus::ReadyForPickup);

    $events = app(ServiceEventRepository::class)->forService($service->id)->items;
    $approved = array_values(array_filter(
        $events,
        static fn ($event): bool => $event->type === ServiceEventType::ReleaseApproved,
    ));

    expect($approved)->toHaveCount(1);
})->with('stores');

it('forbids the technician and the serwisant admin from approving a release', function (string $store, Role $role) {
    usingStore($this, $store);

    $actor = $this->createUser('ktos', $role);
    $service = $this->acceptServiceWithStatus($actor, ServiceStatus::AwaitingApproval);

    $this->actingAsUser($actor)
        ->post('/services/'.$service->id.'/release-approval')
        ->assertForbidden();

    expect(app(ServiceRepository::class)->find($service->id)?->status)->toBe(ServiceStatus::AwaitingApproval);
})->with('stores')->with([Role::Employee, Role::Technician, Role::Admin]);

it('keeps the existing admin account fully operational apart from the approval', function (string $store) {
    usingStore($this, $store);

    $admin = $this->createUser('admin', Role::Admin);
    $service = $this->acceptService($admin);

    // The old admin still creates orders, advances them and manages accounts.
    $this->actingAsUser($admin)->post('/services/'.$service->id.'/advance')->assertRedirect();
    $this->actingAsUser($admin)->get('/admin/users')->assertOk();

    // But the final acceptance belongs to the owner.
    expect($admin->can(Ability::APPROVE_RELEASE))->toBeFalse();
})->with('stores');

it('returns the laptop to the queue with the owner remarks', function (string $store) {
    usingStore($this, $store);

    $owner = $this->createUser('szef', Role::Owner);
    $service = $this->acceptServiceWithStatus($owner, ServiceStatus::AwaitingApproval);

    $this->actingAsUser($owner)
        ->post('/services/'.$service->id.'/release-return', [
            'approval_remarks' => 'Sprawdź jeszcze klawisz Enter i wyczyść wentylator.',
        ])
        ->assertRedirect(route('services.show', $service));

    $fresh = app(ServiceRepository::class)->find($service->id);

    expect($fresh?->status)->toBe(ServiceStatus::WithRemarks)
        ->and($fresh?->approvalRemarks)->toBe('Sprawdź jeszcze klawisz Enter i wyczyść wentylator.');

    $events = app(ServiceEventRepository::class)->forService($service->id)->items;
    $returned = array_values(array_filter(
        $events,
        static fn ($event): bool => $event->type === ServiceEventType::ReturnedWithRemarks,
    ));

    expect($returned)->toHaveCount(1);

    // The remarks are visible in the queue, next to the tile.
    $this->actingAsUser($owner)->get('/?status=with_remarks')
        ->assertOk()
        ->assertSee('Uwagi szefa')
        ->assertSee('Sprawdź jeszcze klawisz Enter');
})->with('stores');

it('requires remarks when returning a release request', function (string $store) {
    usingStore($this, $store);

    $owner = $this->createUser('szef', Role::Owner);
    $service = $this->acceptServiceWithStatus($owner, ServiceStatus::AwaitingApproval);

    $this->actingAsUser($owner)
        ->post('/services/'.$service->id.'/release-return', ['approval_remarks' => ''])
        ->assertSessionHasErrors('approval_remarks');

    expect(app(ServiceRepository::class)->find($service->id)?->status)->toBe(ServiceStatus::AwaitingApproval);
})->with('stores');

it('forbids the technician from returning a release request to the queue', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptServiceWithStatus($user, ServiceStatus::AwaitingApproval);

    $this->actingAsUser($user)
        ->post('/services/'.$service->id.'/release-return', ['approval_remarks' => 'Nie zgadzam się.'])
        ->assertForbidden();
})->with('stores');

it('lets the technician resume a repair returned with remarks', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptServiceWithStatus($user, ServiceStatus::WithRemarks);
    $service = app(ServiceRepository::class)->save($service->with([
        'approval_remarks' => 'Popraw wentylator.',
    ]));

    $this->actingAsUser($user)->post('/services/'.$service->id.'/advance')->assertRedirect();

    $fresh = app(ServiceRepository::class)->find($service->id);

    expect($fresh?->status)->toBe(ServiceStatus::InProgress)
        ->and($fresh?->approvalRemarks)->toBe('Popraw wentylator.');

    $events = app(ServiceEventRepository::class)->forService($service->id)->items;
    $resumed = array_values(array_filter(
        $events,
        static fn ($event): bool => $event->type === ServiceEventType::ResumedRepair,
    ));

    expect($resumed)->toHaveCount(1);
})->with('stores');

it('completes the whole round trip: request, return, resume, request, approve, release', function (string $store) {
    usingStore($this, $store);

    $owner = $this->createUser('szef', Role::Owner);
    $technician = $this->createUser('jan', Role::Employee);
    $service = $this->acceptServiceWithStatus($technician, ServiceStatus::InProgress);

    $repo = app(ServiceRepository::class);
    $url = '/services/'.$service->id;

    // 1. request
    $this->actingAsUser($technician)->post($url.'/advance')->assertRedirect();
    expect($repo->find($service->id)?->status)->toBe(ServiceStatus::AwaitingApproval);

    // 2. owner returns it
    $this->actingAsUser($owner)->post($url.'/release-return', ['approval_remarks' => 'Dociśnij taśmę matrycy.'])->assertRedirect();
    expect($repo->find($service->id)?->status)->toBe(ServiceStatus::WithRemarks);

    // 3. technician resumes
    $this->actingAsUser($technician)->post($url.'/advance')->assertRedirect();
    expect($repo->find($service->id)?->status)->toBe(ServiceStatus::InProgress);

    // 4. request again, owner accepts
    $this->actingAsUser($technician)->post($url.'/advance')->assertRedirect();
    $this->actingAsUser($owner)->post($url.'/release-approval')->assertRedirect();
    expect($repo->find($service->id)?->status)->toBe(ServiceStatus::ReadyForPickup);

    // 5. release exactly like today
    $this->actingAsUser($technician)->post($url.'/release', [
        'released_at' => '2026-11-01T12:30',
        'price' => '150',
        'cost' => '50',
    ])->assertRedirect();

    $released = $repo->find($service->id);

    expect($released?->status)->toBe(ServiceStatus::Released)
        ->and($released?->releasedBy)->toBe($technician->id)
        ->and($released?->approvalRemarks)->toBeNull();

    $types = array_map(
        static fn ($event): string => $event->type->value,
        app(ServiceEventRepository::class)->forService($service->id)->items,
    );

    expect($types)->toContain(ServiceEventType::ReleaseRequested->value)
        ->and($types)->toContain(ServiceEventType::ReturnedWithRemarks->value)
        ->and($types)->toContain(ServiceEventType::ResumedRepair->value)
        ->and($types)->toContain(ServiceEventType::ReleaseApproved->value)
        ->and($types)->toContain(ServiceEventType::Released->value);
})->with('stores');

it('carries the acceptance action in the normal edit panel', function (string $store) {
    usingStore($this, $store);

    $owner = $this->createUser('szef', Role::Owner);
    $service = $this->acceptServiceWithStatus($owner, ServiceStatus::AwaitingApproval);

    // The queue tile for an awaiting order still opens the normal edit panel.
    $this->actingAsUser($owner)->get('/?status=awaiting_approval')
        ->assertOk()
        ->assertSee('data-card-href="'.route('services.edit', $service).'"', false);

    $this->actingAsUser($owner)->get('/services/'.$service->id.'/edit')
        ->assertOk()
        ->assertSee('Akceptacja wydania')
        ->assertSee('Potwierdź')
        ->assertSee('Zwróć z uwagami')
        ->assertSee('Zapisz zmiany')
        ->assertSee('action="'.route('services.approval.approve', $service).'"', false)
        ->assertSee('action="'.route('services.approval.return', $service).'"', false);
})->with('stores');

it('does not offer the acceptance buttons to the technician in the edit panel', function (string $store) {
    usingStore($this, $store);

    $technician = $this->createUser('jan', Role::Employee);
    $service = $this->acceptServiceWithStatus($technician, ServiceStatus::AwaitingApproval);

    $this->actingAsUser($technician)->get('/services/'.$service->id.'/edit')
        ->assertOk()
        ->assertDontSee('Akceptacja wydania')
        ->assertDontSee('Potwierdź');
})->with('stores');

it('warns the account manager when the installation has no owner yet', function (string $store) {
    usingStore($this, $store);

    $admin = $this->createUser('admin', Role::Admin);
    $technician = $this->createUser('jan', Role::Employee);

    // Existing deployment after an upgrade without `users:owner`: the admin
    // still runs the workshop but cannot accept a release — the screen says so.
    $this->actingAsUser($admin)->get('/')
        ->assertOk()
        ->assertSee('Brak konta Szef')
        ->assertSee('users:owner');

    $this->actingAsUser($technician)->get('/')
        ->assertOk()
        ->assertDontSee('Brak konta Szef');

    $this->createUser('szef', Role::Owner);

    $this->actingAsUser($admin)->get('/')
        ->assertOk()
        ->assertDontSee('Brak konta Szef');
})->with('stores');

it('shows the acceptance count only to the owner', function (string $store) {
    usingStore($this, $store);

    $owner = $this->createUser('szef', Role::Owner);
    $technician = $this->createUser('jan', Role::Employee);
    $this->acceptServiceWithStatus($technician, ServiceStatus::AwaitingApproval);
    $this->acceptServiceWithStatus($technician, ServiceStatus::WithRemarks);

    $ownerResponse = $this->actingAsUser($owner)->get('/');

    $ownerResponse->assertOk()
        ->assertSee('do akceptacji');

    // The owner's page title marks the pending acceptance as well.
    expect($ownerResponse->getContent())->toContain('1 do akceptacji');

    $technicianResponse = $this->actingAsUser($technician)->get('/');

    $technicianResponse->assertOk()
        ->assertSee('z uwagami')
        ->assertDontSee('do akceptacji');

    expect($technicianResponse->getContent())->toContain('1 z uwagami');
})->with('stores');

it('offers the short owner actions on the service card', function (string $store) {
    usingStore($this, $store);

    $owner = $this->createUser('szef', Role::Owner);
    $technician = $this->createUser('jan', Role::Employee);
    $service = $this->acceptServiceWithStatus($owner, ServiceStatus::AwaitingApproval);

    $this->actingAsUser($owner)->get('/services/'.$service->id)
        ->assertOk()
        ->assertSee('Potwierdź')
        ->assertSee('Zwróć z uwagami');

    // The technician sees the state, not the owner actions.
    $this->actingAsUser($technician)->get('/services/'.$service->id)
        ->assertOk()
        ->assertSee('Czeka na akceptację szefa')
        ->assertDontSee('Potwierdź');
})->with('stores');

it('keeps the new stages in the queue filter and its counts', function (string $store) {
    usingStore($this, $store);

    $owner = $this->createUser('szef', Role::Owner);
    $service = $this->acceptServiceWithStatus($owner, ServiceStatus::AwaitingApproval);

    $counts = app(ServiceRepository::class)->countsByStatus();

    expect($counts[ServiceStatus::AwaitingApproval->value] ?? 0)->toBe(1);

    $this->actingAsUser($owner)->get('/?status=awaiting_approval')
        ->assertOk()
        ->assertSee($service->deviceModel)
        ->assertSee('Oczekuje na akceptację');

    expect($service->status->shortLabel())->toBe('Akcept.');
})->with('stores');
