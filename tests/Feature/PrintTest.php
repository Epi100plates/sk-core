<?php

declare(strict_types=1);

use App\Contracts\ServiceRepository;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceStatus;
use Carbon\CarbonImmutable;

/*
|--------------------------------------------------------------------------
| F-55 — A4 printouts of the acceptance confirmation and release protocol
|--------------------------------------------------------------------------
*/

it('prints the acceptance confirmation with the reference and the deadline', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, [
        'planned_at' => CarbonImmutable::parse('2026-11-05 15:00:00', 'UTC'),
        'price' => '199.99',
    ]);

    $response = $this->actingAsUser($user)->get('/services/'.$service->id.'/print/acceptance');

    $response->assertOk()
        ->assertSee('Potwierdzenie przyjęcia')
        ->assertSee($service->reference)
        ->assertSee('Dell Latitude 5420')
        ->assertSee('05.11.2026 16:00')
        ->assertSee('199,99 zł')
        ->assertSee('505 123 456');

    // Printing counts as issuing the confirmation (decision D6).
    expect(app(ServiceRepository::class)->find($service->id)?->hasIssuedConfirmation())->toBeTrue();
})->with('stores');

it('keeps the first confirmation date when printing again', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)->get('/services/'.$service->id.'/print/acceptance');

    $first = app(ServiceRepository::class)->find($service->id)?->confirmationIssuedAt;

    $this->travel(2)->hours();
    $this->actingAsUser($user)->get('/services/'.$service->id.'/print/acceptance');

    $second = app(ServiceRepository::class)->find($service->id)?->confirmationIssuedAt;

    expect($second?->format('Y-m-d H:i:s'))->toBe($first?->format('Y-m-d H:i:s'));
})->with('stores');

it('shows the "confirmation issued" marker on the service card', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)->get('/services/'.$service->id.'/print/acceptance');

    $this->actingAsUser($user)->get('/')
        ->assertOk()
        ->assertSee('potwierdzenie wydane');
})->with('stores');

it('prints the release protocol with the price and the release date', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $repo = app(ServiceRepository::class);
    $service = $repo->save($this->acceptService($user)->with([
        'status' => ServiceStatus::Released->value,
        'released_at' => '2026-11-01 11:30:00',
        'released_by' => $user->id,
        'price' => '249.99',
        'cost' => '99.50',
    ]));

    $this->actingAsUser($user)->get('/services/'.$service->id.'/print/release')
        ->assertOk()
        ->assertSee('Protokół wydania')
        ->assertSee($service->reference)
        ->assertSee('249,99 zł')
        ->assertSee('01.11.2026 12:30');
})->with('stores');

it('requires a session to print anything', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->get('/services/'.$service->id.'/print/acceptance')->assertRedirect(route('login'));
    $this->get('/services/'.$service->id.'/print/release')->assertRedirect(route('login'));
})->with('stores');
