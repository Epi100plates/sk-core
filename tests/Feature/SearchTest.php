<?php

declare(strict_types=1);

use App\Contracts\ServiceRepository;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceStatus;

/*
|--------------------------------------------------------------------------
| F-27…F-29 — one search field in the header, filtering the queue
|--------------------------------------------------------------------------
|
| The old separate /search screen is gone: the header field filters the queue in
| place. The same rules still hold (phone in any format, seal, model, serial,
| issue, reference, released orders included), and the renderer is shared with
| the live-search fragment so both show exactly the same list.
|
*/

it('finds a service by a phone fragment written another way (defect D-20)', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['phone' => '600 100 200', 'device_model' => 'Dell']);

    foreach (['600 100 200', '600100200', '+48600100200', '600 100'] as $term) {
        $this->actingAsUser($user)
            ->get('/?q='.urlencode($term))
            ->assertOk()
            ->assertSee($service->reference);
    }
})->with('stores');

it('finds a service by seal number, case insensitive', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['seal_number' => 'PLO-001-2024']);

    $this->actingAsUser($user)->get('/?q=plo-001')->assertOk()->assertSee($service->reference);
})->with('stores');

it('searches the model, serial number, issue and reference as well', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, [
        'device_model' => 'MacBook Air',
        'serial_number' => 'C02XYZ',
        'issue' => 'Pęknięta matryca',
    ]);

    foreach (['MacBook', 'C02XYZ', 'matryca', strtolower($service->reference)] as $term) {
        $this->actingAsUser($user)->get('/?q='.urlencode($term))->assertOk()->assertSee($service->reference);
    }
})->with('stores');

it('includes released services in the search results', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $repo = app(ServiceRepository::class);

    $service = $this->acceptService($user, ['device_model' => 'Wydany Dell']);
    $repo->save($service->with([
        'status' => ServiceStatus::Released->value,
        'released_at' => '2026-11-01 10:00:00',
        'released_by' => $user->id,
    ]));

    $this->actingAsUser($user)->get('/?q=Wydany')
        ->assertOk()
        ->assertSee('Wydany Dell')
        // A mixed result is grouped by stage, so the archive list is not repeated.
        ->assertDontSee('Ostatnio wydane');
})->with('stores');

it('shows the normal queue below three characters', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['device_model' => 'Dell Latitude']);

    $this->actingAsUser($user)->get('/?q=De')
        ->assertOk()
        ->assertSee($service->reference)
        ->assertDontSee('dla „', false);
})->with('stores');

it('shows an empty state when nothing matches', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->get('/?q=nieistniejacy')
        ->assertOk()
        ->assertSee('Brak wyników');
})->with('stores');

it('escapes the search term in the results', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $response = $this->actingAsUser($user)->get('/?q='.urlencode('<script>alert(1)</script>'));

    $response->assertOk()->assertDontSee('<script>alert(1)</script>', false);
})->with('stores');

it('ignores a search parameter that is not a string', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->get('/?q[]=Dell')->assertOk();
})->with('stores');

it('has no second search screen', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->get('/search?q=Dell')->assertNotFound();

    // The archive keeps a date range, not its own search field.
    $this->actingAsUser($user)->get('/released')->assertOk()->assertDontSee('id="q"', false);
})->with('stores');
