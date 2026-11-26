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

it('shows only the chosen stage and offers a way back to the whole queue', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $repo = app(ServiceRepository::class);

    $pending = $this->acceptService($user, ['device_model' => 'Oczekujacy']);
    $inProgress = $repo->save(
        $this->acceptService($user, ['device_model' => 'W trakcie'])
            ->with(['status' => ServiceStatus::InProgress->value])
    );

    $response = $this->actingAsUser($user)->get('/?status=in_progress');

    $response->assertOk()
        ->assertSee('W trakcie')
        // The sidebar is a shortcut to every open job, so the model name can
        // appear there; what must not appear is the pending card in the list.
        ->assertDontSee('/services/'.$pending->id.'/edit', false)
        ->assertSee('Pokaż wszystkie');

    expect($pending->id)->not->toBe($inProgress->id);
})->with('stores');

it('shows every open stage when no stage is chosen', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $this->acceptService($user, ['device_model' => 'Oczekujacy']);
    app(ServiceRepository::class)->save(
        $this->acceptService($user, ['device_model' => 'W trakcie'])
            ->with(['status' => ServiceStatus::InProgress->value])
    );

    $this->actingAsUser($user)->get('/')
        ->assertOk()
        ->assertSee('Oczekujacy')
        ->assertSee('W trakcie')
        ->assertDontSee('Pokaż wszystkie');
})->with('stores');

it('groups the queue under one heading per stage', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $this->acceptService($user, ['device_model' => 'Oczekujacy']);
    app(ServiceRepository::class)->save(
        $this->acceptService($user, ['device_model' => 'W trakcie'])
            ->with(['status' => ServiceStatus::InProgress->value])
    );

    $response = $this->actingAsUser($user)->get('/');

    $response->assertOk()
        ->assertSee('queue-stage-in_progress', false)
        ->assertSee('queue-stage-pending', false)
        ->assertSee('W trakcie naprawy')
        ->assertSee('Oczekujące');
})->with('stores');

it('opens the edit form from the whole tile', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['device_model' => 'Dell Latitude']);

    $this->actingAsUser($user)->get('/')
        ->assertOk()
        ->assertSee('data-card-href="'.route('services.edit', $service).'"', false)
        ->assertSee('Szczegóły');
})->with('stores');

it('labels every fact on a tile so no value stands alone', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $this->acceptService($user, [
        'device_model' => 'Dell Latitude',
        'price' => '199.99',
        'cost' => '89.00',
        'device_password' => 'tajne123',
    ]);

    $this->actingAsUser($user)->get('/')
        ->assertOk()
        // Label above value in a definition list: "210,00 zł / 82,00 zł" on one
        // grey line left the reader to guess which number was which.
        ->assertSee('<dt>Telefon</dt>', false)
        ->assertSee('<dt>Termin</dt>', false)
        ->assertSee('<dt>Cena</dt>', false)
        ->assertSee('<dt>Koszt</dt>', false)
        ->assertSee('<dt>Hasło</dt>', false)
        ->assertSee('199,99 zł')
        ->assertSee('89,00 zł')
        // The complaint is prose on its own row, not a fifth metadata line.
        ->assertSee('class="ticket__issue"', false);
})->with('stores');

it('sends a handed-over tile to its card, not to a missing edit form', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $repo = app(ServiceRepository::class);

    $service = $repo->save(
        $this->acceptService($user, ['device_model' => 'Wydany Dell'])
            ->with([
                'status' => ServiceStatus::Released->value,
                'released_at' => '2026-11-01 10:00:00',
                'released_by' => $user->id,
            ])
    );

    $this->actingAsUser($user)->get('/?q=Wydany')
        ->assertOk()
        ->assertSee('data-card-href="'.route('services.show', $service).'"', false);
})->with('stores');

it('marks the stage strip and the list as async regions', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['device_model' => 'Dell Latitude']);

    $this->actingAsUser($user)->get('/?status=pending')
        ->assertOk()
        // One filter region: counts, hidden term, radios and the reset link are
        // re-rendered together, so they cannot disagree with the list below.
        ->assertSee('data-async-region="queue-filter"', false)
        ->assertSee('data-async-region="queue-results"', false)
        ->assertSee('data-async-form', false)
        ->assertSee('data-async-on-change', false)
        ->assertSee('data-async-link', false)
        // The tile action is applied in place too.
        ->assertSee('action="'.route('services.advance', $service).'" data-async-form', false)
        ->assertSee('id="async-status"', false)
        ->assertSee('data-async-region="flash"', false);
})->with('stores');

it('keeps the search, the way out and the stage filter in one bar', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $this->acceptService($user, ['device_model' => 'Dell Latitude']);

    $response = $this->actingAsUser($user)->get('/?status=pending');

    $response->assertOk()
        // The dark header is gone; one bar carries what it used to hold, and the
        // stage filter is its second row instead of a card of its own.
        ->assertDontSee('class="app-header"', false)
        ->assertSee('class="app-bar"', false)
        ->assertSee('id="global-search"', false)
        ->assertSee('aria-label="Wyloguj z aplikacji"', false)
        ->assertSeeInOrder(['app-bar__title', 'Kolejka napraw'], false)
        ->assertSeeInOrder(['app-bar__row--filters', 'queue-filter', 'stat-strip'], false)
        ->assertSee('Pokaż wszystkie');

    // Every chip is a toggle: the script clears the filter when the chosen one is
    // pressed again (a radio alone cannot be switched off).
    expect(substr_count((string) $response->getContent(), 'data-filter-toggle'))
        ->toBe(count(ServiceStatus::activeCases()));
})->with('stores');

it('keeps the computer queue in the sidebar and puts administration last', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Admin);

    for ($i = 1; $i <= 6; $i++) {
        $this->acceptService($user, ['device_model' => 'Model '.$i]);
    }

    // The intake screen is the proof that the sidebar is shared: its own content
    // never lists services, so every model below comes from the sidebar.
    $this->actingAsUser($user)->get('/services/create')
        ->assertOk()
        ->assertDontSee('studio-komp-mark')
        ->assertSeeInOrder(['Zlecenia', 'Kolejka komputerów', 'Konto', 'Administracja'])
        ->assertSee('Cała kolejka')
        ->assertSee('Model 1')
        ->assertSee('Model 6');
})->with('stores');

it('carries the same bar on every other screen', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->get('/services/create')
        ->assertOk()
        ->assertSee('class="app-bar"', false)
        ->assertSee('id="global-search"', false)
        ->assertSee('aria-label="Wyloguj z aplikacji"', false)
        // Only the queue has stages to filter, so only it fills the second row.
        ->assertDontSee('app-bar__row--filters', false);
})->with('stores');

it('no longer serves a separate list fragment', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    // The async layer fetches the real page and swaps regions, so the old
    // `/queue/results` endpoint is gone (one mechanism, one source of truth).
    $this->actingAsUser($user)->get('/queue/results?q=Dell')->assertNotFound();
})->with('stores');

it('counts services per status on the queue page', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $this->acceptService($user, ['device_model' => 'A']);
    $this->acceptService($user, ['device_model' => 'B']);

    $response = $this->actingAsUser($user)->get('/');

    $response->assertOk()
        ->assertSee('Oczekujące')
        ->assertSee('<span class="stat__value">2</span>', false);
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

    // Paging is an in-place update: the link is a normal href (works without
    // JavaScript) plus the attribute the async layer listens on.
    $this->actingAsUser($user)->get('/')
        ->assertOk()
        ->assertSee('page=2', false)
        ->assertSee('data-async-link data-async-scroll', false);
})->with('stores');

it('shows an empty state when there is nothing in the queue', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->get('/')
        ->assertOk()
        ->assertSee('Kolejka jest pusta');
})->with('stores');
