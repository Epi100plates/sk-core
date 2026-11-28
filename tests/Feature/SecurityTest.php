<?php

declare(strict_types=1);

use App\Contracts\ServiceEventRepository;
use App\Contracts\ServiceRepository;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceStatus;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Security headers, CSRF and the "no public pages" decision (D4, D-05, D-52)
|--------------------------------------------------------------------------
*/

it('sends the security headers with a strict CSP', function (string $uri) {
    $response = $this->get($uri);

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->not->toContain('unsafe-inline')
        ->and($csp)->not->toContain('unsafe-eval');
})->with(['/login', '/manifest.webmanifest', '/offline']);

it('has no public status lookup or legacy page', function (string $uri) {
    $this->get($uri)->assertNotFound();
})->with([
    '/lookup',
    '/reklamacja.php',
    '/reklamacja_nr_tel.php',
    '/reklamacja_plomba.php',
    '/wyswietlanie.php',
    '/wyswietlanie_phone.php',
    '/wydane.php',
    '/dodawanie_laptopa.php',
    '/main_phone.php',
    '/setup.php',
    '/verify-pwa.php',
]);

it('never serves the JSON data files or logs over HTTP', function (string $uri) {
    $this->get($uri)->assertNotFound();
})->with([
    '/storage/app/data/services.json',
    '/storage/app/data/users.json',
    '/storage/logs/laravel.log',
    '/.env',
    '/../.env',
]);

it('includes a CSRF token in every form', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);
    // The release form only exists for a laptop on the "Do wydania" counter.
    $service = app(ServiceRepository::class)->save($service->with(['status' => ServiceStatus::ReadyForPickup->value]));

    foreach ([
        '/services/create',
        '/services/'.$service->id.'/edit',
        '/services/'.$service->id.'/release',
        '/account/password',
    ] as $uri) {
        $response = $this->actingAsUser($user)->get($uri);

        $response->assertOk();
        expect($response->getContent())->toContain('name="_token"');
    }
})->with('stores');

it('rejects a mutation without a CSRF token', function () {
    // CSRF is skipped while the framework thinks it runs unit tests, so pretend
    // to be a normal environment for this one request.
    $this->app['env'] = 'local';

    $this->post('/login', ['login' => 'jan', 'password' => 'cokolwiek'])
        ->assertStatus(419)
        ->assertSee('Sesja wygasła');
});

it('serves the web manifest with existing icons and shortcuts (defect D-29)', function () {
    $response = $this->get('/manifest.webmanifest');

    $response->assertOk();

    $payload = $response->json();

    expect($payload['name'])->toBe((string) config('app.name'))
        ->and($payload['start_url'])->toStartWith('http')
        ->and($payload['shortcuts'])->not->toBeEmpty();

    foreach ($payload['icons'] as $icon) {
        $path = parse_url((string) $icon['src'], PHP_URL_PATH);
        $relative = ltrim((string) $path, '/');

        expect(is_file(public_path($relative)))->toBeTrue("Ikona {$relative} nie istnieje");
    }

    foreach ($payload['shortcuts'] as $shortcut) {
        expect((string) $shortcut['url'])->toStartWith('http');
    }
});

it('renders the offline page without any customer data', function () {
    $response = $this->get('/offline');

    $response->assertOk()
        ->assertSee('Brak połączenia')
        ->assertDontSee('505 123 456');
});

it('declares every public and protected route explicitly', function () {
    $public = array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        static fn ($route): bool => ! in_array('auth', $route->gatherMiddleware(), true),
    ));

    $names = array_map(static fn ($route): string => (string) $route->uri(), $public);

    expect($names)->toContain('login')
        ->and($names)->toContain('manifest.webmanifest')
        ->and($names)->toContain('offline')
        ->and($names)->toContain('__install');
});

it('sends the security headers with error and redirect responses too', function () {
    // A 404 and a guest redirect are rendered outside the middleware stack, so
    // they used to arrive without CSP or X-Frame-Options.
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->get('/')
        ->assertRedirect('/login')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy');
});

it('answers with 404 instead of a stack trace for a missing record', function (string $uri) {
    usingStore($this, 'json');

    $user = $this->createUser('jan', Role::Employee);

    $this->actingAsUser($user)->get($uri)->assertNotFound();
})->with([
    '/services/999999',
    '/services/999999/edit',
    '/services/999999/release',
    '/photos/999999',
    '/admin/users/999999/edit',
]);

it('ignores an intended url pointing at another host after login', function () {
    usingStore($this, 'json');

    $user = $this->createUser('jan', Role::Employee);

    $response = $this
        ->withSession(['url.intended' => 'http://evil.example/services'])
        ->post('/login', ['login' => $user->login, 'password' => 'TajneHaslo123']);

    $response->assertRedirect(route('services.queue'));
    expect((string) $response->headers->get('Location'))->not->toContain('evil.example');
});

it('never writes the device password into the audit trail', function () {
    usingStore($this, 'json');

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user, ['device_password' => 'tajne-haslo']);

    $events = app(ServiceEventRepository::class)->forService($service->id);
    $stored = json_encode(array_map(static fn ($event): array => $event->toArray(), $events->items));

    expect((string) $stored)->not->toContain('tajne-haslo');

    // The password is visible by default on the card, so it appears exactly once
    // in the HTML. A second occurrence would mean it leaked into the history list
    // or into an attribute that survives hiding.
    $html = (string) $this->actingAsUser($user)->get('/services/'.$service->id)->getContent();

    expect(substr_count($html, 'tajne-haslo'))->toBe(1);

    $this->actingAsUser($user)->get('/services/'.$service->id)
        ->assertSee('tajne-haslo')
        ->assertSee('aria-pressed="true"', false)
        ->assertSee('Ukryj hasło do urządzenia');
});

it('shows the device password on the queue tile as well', function () {
    usingStore($this, 'json');

    $user = $this->createUser('jan', Role::Employee);
    $this->acceptService($user, ['device_password' => 'haslo-na-kafelku']);

    $this->actingAsUser($user)->get('/')
        ->assertOk()
        ->assertSee('haslo-na-kafelku');
});
