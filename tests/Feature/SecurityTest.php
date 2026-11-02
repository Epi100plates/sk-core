<?php

declare(strict_types=1);

use App\Domain\Enums\Role;
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

    $this->post('/login', ['login' => 'jan', 'password' => 'cokolwiek'])->assertStatus(419);
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
