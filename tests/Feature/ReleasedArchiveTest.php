<?php

declare(strict_types=1);

use App\Contracts\ServiceRepository;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceStatus;

/*
|--------------------------------------------------------------------------
| F-26 — the archive of released services (async UI contract v1)
|--------------------------------------------------------------------------
|
| The screen updates in place through the shared async layer, without inline
| JavaScript. That layer only finds its work through declarative markup, so the
| contract below is the test: a results region, a date-range form and a toolbar
| region that renders the fields together with the clear link.
|
*/

/** Stores an accepted service as released at the given UTC moment. */
function releasedArchiveEntry(ServiceRecord $service, UserRecord $actor, string $releasedAt = '2026-11-10 10:00:00'): ServiceRecord
{
    return app(ServiceRepository::class)->save($service->with([
        'status' => ServiceStatus::Released->value,
        'released_at' => $releasedAt,
        'released_by' => $actor->id,
    ]));
}

/** The opening tag of the archive date-range form. */
function releasedArchiveFormTag(string $html): string
{
    preg_match('/<form\b[^>]*\baction="'.preg_quote(route('services.released'), '/').'"[^>]*>/', $html, $matches);

    return $matches[0] ?? '';
}

/** The inner HTML of the date-range form (no other form on the page matches it). */
function releasedArchiveFormBody(string $html): string
{
    preg_match('/<form\b[^>]*\baction="'.preg_quote(route('services.released'), '/').'"[^>]*>(.*?)<\/form>/s', $html, $matches);

    return $matches[1] ?? '';
}

/** The inner HTML of the region that carries the date fields and the clear link. */
function releasedArchiveFilterRegion(string $html): string
{
    preg_match('/<div class="app-card-body" data-async-region="released-filter">(.*?)<\/form>/s', $html, $matches);

    return $matches[1] ?? '';
}

it('shows released services for the chosen date range', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    releasedArchiveEntry($this->acceptService($user, ['device_model' => 'Dell w zakresie']), $user, '2026-11-10 10:00:00');
    releasedArchiveEntry($this->acceptService($user, ['device_model' => 'Lenovo poza zakresem']), $user, '2026-09-01 10:00:00');

    $response = $this->actingAsUser($user)->get('/released?from=2026-11-10&to=2026-11-10');

    $response->assertOk()
        ->assertSee('Dell w zakresie')
        ->assertDontSee('Lenovo poza zakresem')
        ->assertSee('value="2026-11-10"', false);
})->with('stores');

it('exposes the markup the async layer needs, including paginated links', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    // 26 released services: one page holds 25, so the archive renders pagination.
    for ($i = 1; $i <= 26; $i++) {
        releasedArchiveEntry($this->acceptService($user, ['device_model' => 'Model '.$i]), $user);
    }

    $response = $this->actingAsUser($user)->get('/released');

    $response->assertOk()
        ->assertSee('data-async-region="released-results"', false)
        ->assertSee('data-async-region="released-filter"', false);

    $html = (string) $response->getContent();

    expect(releasedArchiveFormTag($html))
        ->toContain('method="get"')
        ->toContain('data-async-form')
        ->toContain('data-async-on-change')
        // The shared layer replaces data-auto-submit, so the old hook must go.
        ->not->toContain('data-auto-submit');

    // The toolbar is a region too: "Wyczyść zakres" must bring the date fields
    // back empty from the server. The form submits on `change` only, i.e. after
    // the picker committed, so the field is never replaced mid-typing.
    $body = releasedArchiveFormBody($html);
    $filterAt = strpos($body, 'data-async-region="released-filter"');

    expect($filterAt)->not->toBeFalse()
        ->and(strpos($body, 'name="from"'))->toBeGreaterThan($filterAt)
        ->and(strpos($body, 'name="to"'))->toBeGreaterThan($filterAt)
        ->and(releasedArchiveFilterRegion($html))->toContain('name="from"')
        ->toContain('name="to"');

    // Pagination links are same-screen updates at the top of the list.
    preg_match('/<nav[^>]*aria-label="Paginacja">(.*?)<\/nav>/s', $html, $nav);

    expect($nav[1] ?? '')->toContain('data-async-link')
        ->toContain('data-async-scroll')
        ->toContain('page=2');
})->with('stores');

it('survives an absent or unparsable date range', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    releasedArchiveEntry($this->acceptService($user, ['device_model' => 'Dell wydany']), $user);

    $this->actingAsUser($user)->get('/released')
        ->assertOk()
        ->assertSee('Dell wydany')
        ->assertSee('data-async-region="released-results"', false);

    // Garbage is treated as "no range" instead of a 500, and stays in the inputs.
    $this->actingAsUser($user)->get('/released?from=nie-data&to=2026-13-45')
        ->assertOk()
        ->assertSee('Dell wydany')
        ->assertSee('value="nie-data"', false);

    // A valid range with no releases still renders the empty state inside the region.
    $this->actingAsUser($user)->get('/released?from=2020-01-01&to=2020-01-02')
        ->assertOk()
        ->assertSee('Brak wydanych zleceń')
        ->assertSee('data-async-region="released-results"', false);
})->with('stores');

it('keeps working as a plain GET form without JavaScript', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);

    releasedArchiveEntry($this->acceptService($user, ['device_model' => 'Dell w zakresie']), $user, '2026-11-10 10:00:00');
    releasedArchiveEntry($this->acceptService($user, ['device_model' => 'Lenovo poza zakresem']), $user, '2026-09-01 10:00:00');

    $response = $this->actingAsUser($user)->get('/released?from=2026-11-10&to=2026-11-10');

    $response->assertOk()
        ->assertSee('Dell w zakresie')
        ->assertDontSee('Lenovo poza zakresem');

    // The button submits the same GET form with JavaScript disabled.
    expect(releasedArchiveFormTag((string) $response->getContent()))
        ->toContain('method="get"')
        ->toContain('action="'.route('services.released').'"');

    expect(releasedArchiveFormBody((string) $response->getContent()))
        ->toContain('type="submit"')
        ->toContain('Pokaż');

    // "Wyczyść zakres" is an ordinary link to the unfiltered screen.
    $reset = releasedArchiveFilterRegion((string) $response->getContent());

    expect($reset)->toContain('Wyczyść zakres')
        ->toContain('<a')
        ->toContain('href="'.route('services.released').'"')
        ->toContain('data-async-link');

    // Without a filter the region keeps the fields (so the next swap can update
    // them) and simply renders no clear link.
    $plain = $this->actingAsUser($user)->get('/released');

    $plain->assertOk()->assertSee('data-async-region="released-filter"', false);

    expect(releasedArchiveFilterRegion((string) $plain->getContent()))
        ->toContain('name="from"')
        ->not->toContain('Wyczyść zakres');
})->with('stores');
