<?php

declare(strict_types=1);

use App\Contracts\ServiceRepository;
use App\Domain\Data\ServiceQuery;
use App\Domain\Data\ServiceRecord;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceStatus;

/*
|--------------------------------------------------------------------------
| F-21, F-22, F-32, F-46 — the queue
|--------------------------------------------------------------------------
*/

it('orders the queue by status, express flag and promised date, final states last', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $repo = app(ServiceRepository::class);

    $make = function (string $model, ServiceStatus $status, bool $express, string $planned) use ($user, $repo): ServiceRecord {
        $service = $this->acceptService($user, [
            'device_model' => $model,
            'is_express' => $express,
            'planned_at' => new DateTimeImmutable($planned),
        ]);

        return $status === ServiceStatus::Pending ? $service : $repo->save($service->with(['status' => $status->value]));
    };

    $make('Pending later', ServiceStatus::Pending, false, '2026-11-10 10:00:00');
    $make('Pending older', ServiceStatus::Pending, false, '2026-11-03 10:00:00');
    $make('In progress', ServiceStatus::InProgress, false, '2026-11-20 10:00:00');
    $make('Express pending', ServiceStatus::Pending, true, '2026-11-25 10:00:00');
    $make('Awaiting parts', ServiceStatus::AwaitingParts, false, '2026-11-01 10:00:00');
    $make('Ready for pickup', ServiceStatus::ReadyForPickup, false, '2026-11-02 10:00:00');
    $make('Released express', ServiceStatus::Released, true, '2026-10-01 10:00:00');

    $response = $this->actingAsUser($user)->get('/');

    $response->assertOk();

    $order = $repo->paginate(ServiceQuery::queue(ServiceStatus::activeCases(), 50))->items;
    $names = array_map(static fn (ServiceRecord $service): string => $service->deviceModel, $order);

    expect($names)->toBe([
        'In progress',
        'Express pending',
        'Pending older',
        'Pending later',
        'Awaiting parts',
        'Ready for pickup',
    ]);
})->with('stores');

it('never shows a released service with an action in the queue', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['device_model' => 'Wydany laptop']);

    $repo = app(ServiceRepository::class);
    $released = $repo->save($service->with([
        'status' => ServiceStatus::Released->value,
        'released_at' => '2026-11-01 10:00:00',
        'released_by' => $user->id,
    ]));

    $response = $this->actingAsUser($user)->get('/');

    $response->assertOk()
        ->assertSee('Ostatnio wydane')
        ->assertSee($released->reference)
        ->assertDontSee('Rozpocznij naprawę');

    // The "advance" endpoint refuses a final service anyway (defect D-46).
    $this->actingAsUser($user)
        ->post('/services/'.$released->id.'/advance')
        ->assertStatus(422);
})->with('stores');

it('filters the queue by status and keeps the filter in the URL', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $repo = app(ServiceRepository::class);

    $pending = $this->acceptService($user, ['device_model' => 'Oczekujacy']);
    $inProgress = $repo->save(
        $this->acceptService($user, ['device_model' => 'W trakcie'])
            ->with(['status' => ServiceStatus::InProgress->value])
    );

    $response = $this->actingAsUser($user)->get('/?status[]=in_progress');

    $response->assertOk()
        ->assertSee('W trakcie')
        ->assertDontSee('Oczekujacy');

    expect($pending->id)->not->toBe($inProgress->id);
})->with('stores');

it('counts services per status on the queue page', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $this->acceptService($user, ['device_model' => 'A']);
    $this->acceptService($user, ['device_model' => 'B']);

    $response = $this->actingAsUser($user)->get('/');

    $response->assertOk()
        ->assertSee('Oczekujące:')
        ->assertSee('2');
})->with('stores');

it('paginates the queue at 50 services', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $repo = app(ServiceRepository::class);

    for ($i = 1; $i <= 55; $i++) {
        $this->acceptService($user, ['device_model' => 'Model '.$i]);
    }

    $first = $repo->paginate(ServiceQuery::queue(ServiceStatus::activeCases(), 50, 1));
    $second = $repo->paginate(ServiceQuery::queue(ServiceStatus::activeCases(), 50, 2));

    expect($first->count())->toBe(50)
        ->and($second->count())->toBe(5)
        ->and($first->total)->toBe(55)
        ->and($first->lastPage())->toBe(2);
})->with('stores');

it('shows an empty state when there is nothing in the queue', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->get('/')
        ->assertOk()
        ->assertSee('Kolejka jest pusta');
})->with('stores');
