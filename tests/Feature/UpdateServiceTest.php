<?php

declare(strict_types=1);

use App\Contracts\ServiceEventRepository;
use App\Contracts\ServiceRepository;
use App\Domain\Data\ServiceRecord;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceEventType;
use App\Domain\Enums\ServiceStatus;
use App\Http\Requests\ServiceRequest;
use Carbon\CarbonImmutable;

/*
|--------------------------------------------------------------------------
| F-34…F-36 and the runtime regressions D-44, D-47, D-48
|--------------------------------------------------------------------------
*/

function editPayload(ServiceRecord $service, array $overrides = []): array
{
    return array_merge([
        'phone' => $service->phone,
        'device_model' => $service->deviceModel,
        'issue' => $service->issue,
        'serial_number' => $service->serialNumber,
        'device_password' => $service->devicePassword,
        'notes' => $service->notes,
        'is_express' => $service->isExpress ? '1' : '0',
        'planned_at' => ServiceRequest::fromRecordToInput($service->plannedAt),
        'price' => $service->price,
        'cost' => $service->cost,
        'seal_number' => $service->sealNumber,
        'status' => $service->status->value,
    ], $overrides);
}

it('does not move the promised date when a form is saved without changes (defect D-44)', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, [
        'planned_at' => CarbonImmutable::parse('2026-11-05 16:42:46', 'UTC'),
        'is_express' => true,
        'price' => '199.99',
        'cost' => '50.00',
    ]);

    $before = app(ServiceRepository::class)->find($service->id);
    $eventsBefore = app(ServiceEventRepository::class)->forService($service->id)->total;

    $response = $this->actingAsUser($user)->put('/services/'.$service->id, editPayload($service));

    $response->assertRedirect(route('services.show', $service));
    $response->assertSessionHas('status', 'Brak zmian — nic nie zostało zapisane.');

    $after = app(ServiceRepository::class)->find($service->id);

    expect($after?->plannedAt->format('Y-m-d H:i:s'))->toBe('2026-11-05 16:42:46')
        ->and($after?->version)->toBe($before?->version)
        ->and($after?->isExpress)->toBeTrue()
        ->and($after?->price)->toBe('199.99')
        ->and($after?->cost)->toBe('50.00')
        ->and(app(ServiceEventRepository::class)->forService($service->id)->total)->toBe($eventsBefore);
})->with('stores');

it('keeps the cost when only another field is edited (defect D-47)', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['cost' => '50.00', 'notes' => null]);

    $payload = editPayload($service, ['notes' => 'Klient prosi o kontakt']);
    unset($payload['cost']); // a form without the cost field must not zero it

    $this->actingAsUser($user)->put('/services/'.$service->id, $payload)->assertRedirect();

    $after = app(ServiceRepository::class)->find($service->id);

    expect($after?->cost)->toBe('50.00')
        ->and($after?->notes)->toBe('Klient prosi o kontakt');
})->with('stores');

it('keeps the express and confirmation flags unchanged when saving (defect D-48)', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['is_express' => false]);

    $this->actingAsUser($user)
        ->put('/services/'.$service->id, editPayload($service, ['is_express' => '0', 'device_model' => 'Nowy model']))
        ->assertRedirect();

    $after = app(ServiceRepository::class)->find($service->id);

    expect($after?->isExpress)->toBeFalse()
        ->and($after?->deviceModel)->toBe('Nowy model')
        ->and($after?->hasIssuedConfirmation())->toBeFalse();
})->with('stores');

it('records a status change from the edit form as status_changed', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)
        ->put('/services/'.$service->id, editPayload($service, ['status' => ServiceStatus::InProgress->value]))
        ->assertRedirect();

    $fresh = app(ServiceRepository::class)->find($service->id);
    $events = app(ServiceEventRepository::class)->forService($service->id);

    expect($fresh?->status)->toBe(ServiceStatus::InProgress);

    $statusEvents = array_values(array_filter(
        $events->items,
        static fn ($event): bool => $event->type === ServiceEventType::StatusChanged,
    ));

    expect($statusEvents)->not->toBeEmpty()
        ->and($statusEvents[0]->fromStatus)->toBe('pending')
        ->and($statusEvents[0]->toStatus)->toBe('in_progress');
})->with('stores');

it('refuses to release or cancel through the edit form', function (string $store, string $status) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)
        ->put('/services/'.$service->id, editPayload($service, ['status' => $status]))
        ->assertStatus(422);

    expect(app(ServiceRepository::class)->find($service->id)?->status)->toBe(ServiceStatus::Pending);
})->with('stores')->with(['released', 'cancelled']);

it('refuses a transition that the workflow does not allow', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $repo = app(ServiceRepository::class);

    $service = $this->acceptService($user);
    $service = $repo->save($service->with(['status' => ServiceStatus::ReadyForPickup->value]));

    $this->actingAsUser($user)
        ->put('/services/'.$service->id, editPayload($service, ['status' => ServiceStatus::Pending->value]))
        ->assertStatus(422);
})->with('stores');

it('normalises the seal number and warns about a duplicate', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $first = $this->acceptService($user, ['seal_number' => 'PLO-001']);
    $second = $this->acceptService($user, ['seal_number' => null]);

    $this->actingAsUser($user)
        ->put('/services/'.$second->id, editPayload($second, ['seal_number' => 'plo-001']))
        ->assertRedirect();

    $fresh = app(ServiceRepository::class)->find($second->id);

    expect($fresh?->sealNumber)->toBe('PLO-001')
        ->and($first->sealNumber)->toBe('PLO-001');
})->with('stores');

it('shows the edit form only for services that are still open', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)->get('/services/'.$service->id.'/edit')
        ->assertOk()
        ->assertSee($service->deviceModel)
        ->assertSee($service->reference);

    $released = app(ServiceRepository::class)->save($service->with(['status' => ServiceStatus::Released->value]));

    $this->actingAsUser($user)->get('/services/'.$released->id.'/edit')->assertForbidden();
})->with('stores');

it('pre-fills the edit form with the stored values', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, [
        'phone' => '505 123 456',
        'device_model' => 'Dell Latitude 5420',
        'serial_number' => 'SN-XYZ',
        'device_password' => 'haslo123',
        'notes' => 'Uwaga wewnętrzna',
        'price' => '199.99',
        'cost' => '50.00',
        'seal_number' => 'PLO-500',
        'is_express' => true,
    ]);

    $response = $this->actingAsUser($user)->get('/services/'.$service->id.'/edit');

    $response->assertOk()
        ->assertSee('value="Dell Latitude 5420"', false)
        ->assertSee('value="SN-XYZ"', false)
        ->assertSee('value="haslo123"', false)
        ->assertSee('value="PLO-500"', false)
        ->assertSee('199,99', false)
        ->assertSee('50,00', false)
        ->assertSee('Uwaga wewnętrzna')
        ->assertSee('checked', false);
})->with('stores');
