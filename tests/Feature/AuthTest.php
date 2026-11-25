<?php

declare(strict_types=1);

use App\Contracts\UserRepository;
use App\Domain\Enums\Role;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| F-01…F-05, D-04, D-07 — authentication
|--------------------------------------------------------------------------
*/

it('logs an employee in and redirects to the queue', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee, 'BardzoDlugieHaslo1');

    $response = $this->post('/login', [
        'login' => 'jan',
        'password' => 'BardzoDlugieHaslo1',
    ]);

    $response->assertRedirect(route('services.queue'));
    $this->assertAuthenticatedAs($user);

    $fresh = app(UserRepository::class)->find($user->id);
    expect($fresh?->lastLoginAt)->not->toBeNull();
})->with('stores');

it('shows one generic message for a wrong password and for an unknown account', function (string $store) {
    usingStore($this, $store);

    $this->createUser('jan', Role::Employee, 'BardzoDlugieHaslo1');

    $wrongPassword = $this->from('/login')->post('/login', [
        'login' => 'jan',
        'password' => 'zle-haslo',
    ]);

    $unknownAccount = $this->from('/login')->post('/login', [
        'login' => 'nie-ma-takiego',
        'password' => 'zle-haslo',
    ]);

    expect(session('errors')->first('login'))->toBe('Nieprawidłowy login lub hasło.');

    $wrongPassword->assertRedirect('/login');
    $unknownAccount->assertRedirect('/login');
    $this->assertGuest();
})->with('stores');

it('blocks a deactivated account', function (string $store) {
    usingStore($this, $store);

    $this->createUser('jan', Role::Employee, 'BardzoDlugieHaslo1', active: false);

    $this->from('/login')
        ->post('/login', ['login' => 'jan', 'password' => 'BardzoDlugieHaslo1'])
        ->assertRedirect('/login');

    $this->assertGuest();
})->with('stores');

it('throttles the sixth login attempt', function (string $store) {
    usingStore($this, $store);

    $this->createUser('jan', Role::Employee, 'BardzoDlugieHaslo1');

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['login' => 'jan', 'password' => 'zle-haslo']);
    }

    $response = $this->post('/login', ['login' => 'jan', 'password' => 'zle-haslo']);

    $response->assertStatus(429);
})->with('stores');

it('rotates the session id on login', function (string $store) {
    usingStore($this, $store);

    $this->createUser('jan', Role::Employee, 'BardzoDlugieHaslo1');

    $this->get('/login');
    $before = session()->getId();

    $this->post('/login', ['login' => 'jan', 'password' => 'BardzoDlugieHaslo1']);

    expect(session()->getId())->not->toBe($before);
})->with('stores');

it('logs out with a POST and invalidates the session', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee, 'BardzoDlugieHaslo1');

    $this->actingAsUser($user)
        ->post('/logout')
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Wylogowano.');

    $this->assertGuest();
})->with('stores');

it('rejects a GET logout', function () {
    $this->get('/logout')->assertStatus(405);
});

it('never executes a payload passed in the error query parameter', function (string $store) {
    usingStore($this, $store);

    $payload = '<img src=x onerror=alert(1)>';

    $response = $this->get('/login?error='.urlencode($payload));

    $response->assertOk();
    $response->assertDontSee($payload, false);
})->with('stores');

it('redirects a guest from every protected route', function (string $uri) {
    $this->get($uri)->assertRedirect(route('login'));
})->with([
    '/',
    '/services/create',
    '/released',
    '/admin/users',
    '/account/password',
]);

it('does not remember a user after logout', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee, 'BardzoDlugieHaslo1');

    $this->actingAsUser($user);
    expect(Auth::check())->toBeTrue();

    $this->post('/logout');
    expect(Auth::check())->toBeFalse();
})->with('stores');
