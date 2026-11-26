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
| F-07…F-20 — accepting a laptop, both data stores
|--------------------------------------------------------------------------
*/

it('accepts a service with every field and writes the audit trail', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $response = $this->actingAsUser($user)->post('/services', [
        'phone' => '505 123 456',
        'device_model' => 'Dell Latitude 5420',
        'issue' => "Nie ładuje się.\nKlient zgłasza iskry z zasilacza.",
        'serial_number' => 'SN-ABC-123',
        'device_password' => 'tajne123',
        'notes' => 'Klient czeka przy ladzie.',
        'is_express' => '1',
        'planned_at' => '2026-11-05T15:00',
        'price' => '199,99',
        'cost' => '80,50',
        'seal_number' => 'plo-001/2026',
    ]);

    $service = app(ServiceRepository::class)->all()[0] ?? null;

    expect($service)->toBeInstanceOf(ServiceRecord::class);

    /** @var ServiceRecord $service */
    $response->assertRedirect(route('services.show', $service));

    expect($service->phone)->toBe('505 123 456')
        ->and($service->phoneNormalized)->toBe('+48505123456')
        ->and($service->deviceModel)->toBe('Dell Latitude 5420')
        ->and($service->issue)->toContain("Nie ładuje się.\nKlient")
        ->and($service->serialNumber)->toBe('SN-ABC-123')
        ->and($service->devicePassword)->toBe('tajne123')
        ->and($service->notes)->toBe('Klient czeka przy ladzie.')
        ->and($service->isExpress)->toBeTrue()
        ->and($service->price)->toBe('199.99')
        ->and($service->cost)->toBe('80.50')
        ->and($service->sealNumber)->toBe('PLO-001/2026')
        ->and($service->status)->toBe(ServiceStatus::Pending)
        ->and($service->acceptedBy)->toBe($user->id)
        ->and($service->reference)->toMatch('/^SRV-\d{4}-\d{6}$/');

    // 15:00 in Warsaw is 13:00 UTC in November (defect D-45: no double shift).
    expect($service->plannedAt->utc()->format('Y-m-d H:i'))
        ->toBe('2026-11-05 14:00');

    $events = app(ServiceEventRepository::class)->forService($service->id);

    expect($events->total)->toBe(1)
        ->and($events->items[0]->type)->toBe(ServiceEventType::Accepted)
        ->and($events->items[0]->actorId)->toBe($user->id)
        ->and($events->items[0]->changes)->toHaveKey('device_model');
})->with('stores');

it('rejects an invalid acceptance payload', function (string $store, array $payload, string $field) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->post('/services', $payload)->assertSessionHasErrors($field);

    expect(app(ServiceRepository::class)->count())->toBe(0);
})->with('stores')->with([
    'missing phone' => [['phone' => ''], 'phone'],
    'garbage phone' => [['phone' => 'abc'], 'phone'],
    'missing model' => [['device_model' => ''], 'device_model'],
    'missing issue' => [['issue' => ''], 'issue'],
    'missing planned date' => [['planned_at' => ''], 'planned_at'],
    'negative price' => [['price' => '-5'], 'price'],
    'bad seal characters' => [['seal_number' => 'PLO#1'], 'seal_number'],
]);

it('defaults the planned date to the next working day at 15:00', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 10:15:00', 'Europe/Warsaw'));

    $value = ServiceRequest::defaultPlannedAt();
    $timezone = (string) config('app.display_timezone');

    $default = CarbonImmutable::parse($value, $timezone);

    expect($default->format('H:i'))->toBe('15:00')
        ->and($default->toDateString())->toBe('2026-11-30')
        ->and($default->isWeekday())->toBeTrue()
        ->and($default->isFuture())->toBeTrue();
});

it('hides the seal number when accepting and shows it when editing', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->get('/services/create')
        ->assertOk()
        ->assertDontSee('id="seal_number"', false)
        ->assertSee('Opcje');

    $service = $this->acceptService($user);

    $this->actingAsUser($user)->get('/services/'.$service->id.'/edit')
        ->assertOk()
        ->assertSee('id="seal_number"', false);
})->with('stores');

it('marks the confirmation as issued and opens the print view when asked', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $response = $this->actingAsUser($user)->post('/services', [
        'phone' => '505 123 456',
        'device_model' => 'Lenovo T490',
        'issue' => 'Bateria do wymiany.',
        'planned_at' => '2026-11-05T15:00',
        'price' => '100',
        'cost' => '20',
        'print_confirmation' => '1',
    ]);

    $service = app(ServiceRepository::class)->all()[0];

    $response->assertRedirect(route('print.acceptance', $service));

    $fresh = app(ServiceRepository::class)->find($service->id);
    expect($fresh?->hasIssuedConfirmation())->toBeTrue();
})->with('stores');

it('accepts a service without photos and without a device password', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->post('/services', [
        'phone' => '+48 505 123 456',
        'device_model' => 'Acer Aspire 5',
        'issue' => 'Brak dźwięku.',
        'planned_at' => '2026-11-05T15:00',
        'price' => '0',
        'cost' => '0',
    ])->assertRedirect();

    $service = app(ServiceRepository::class)->all()[0];

    expect($service->devicePassword)->toBeNull()
        ->and($service->price)->toBe('0.00');
})->with('stores');
