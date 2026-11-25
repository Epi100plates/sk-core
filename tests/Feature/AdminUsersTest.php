<?php

declare(strict_types=1);

use App\Contracts\ServiceRepository;
use App\Contracts\UserRepository;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceStatus;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| F-06 — account management and the three role matrix
|--------------------------------------------------------------------------
*/

it('lets an admin create an account with a hashed password', function (string $store) {
    usingStore($this, $store);

    $admin = $this->createUser('admin', Role::Admin);

    $this->actingAsUser($admin)->post('/admin/users', [
        'name' => 'Tomek',
        'login' => 'tomek',
        'role' => Role::Technician->value,
        'password' => 'BardzoDlugieHaslo1',
        'password_confirmation' => 'BardzoDlugieHaslo1',
        'is_active' => '1',
    ])->assertRedirect(route('admin.users.index'));

    $created = app(UserRepository::class)->findByLogin('tomek');

    expect($created)->not->toBeNull()
        ->and($created?->role)->toBe(Role::Technician)
        ->and($created?->password)->not->toBe('BardzoDlugieHaslo1')
        ->and(Hash::check('BardzoDlugieHaslo1', (string) $created?->password))->toBeTrue()
        ->and($created?->mustChangePassword)->toBeTrue();
})->with('stores');

it('refuses a duplicate login', function (string $store) {
    usingStore($this, $store);

    $admin = $this->createUser('admin', Role::Admin);
    $this->createUser('tomek');

    $this->actingAsUser($admin)->post('/admin/users', [
        'name' => 'Tomek 2',
        'login' => 'tomek',
        'role' => Role::Employee->value,
        'password' => 'BardzoDlugieHaslo1',
        'password_confirmation' => 'BardzoDlugieHaslo1',
    ])->assertSessionHasErrors('login');
})->with('stores');

it('requires a password of at least 12 characters', function (string $store) {
    usingStore($this, $store);

    $admin = $this->createUser('admin', Role::Admin);

    $this->actingAsUser($admin)->post('/admin/users', [
        'name' => 'Tomek',
        'login' => 'tomek',
        'role' => Role::Employee->value,
        'password' => 'krotkie',
        'password_confirmation' => 'krotkie',
    ])->assertSessionHasErrors('password');
})->with('stores');

it('forbids the user panel for employee and technician', function (string $store, Role $role) {
    usingStore($this, $store);

    $user = $this->createUser('ktos', $role);

    $this->actingAsUser($user)->get('/admin/users')->assertForbidden();
    $this->actingAsUser($user)->get('/admin/users/create')->assertForbidden();
})->with('stores')->with([Role::Employee, Role::Technician]);

it('lets an admin deactivate an account, which blocks logging in', function (string $store) {
    usingStore($this, $store);

    $admin = $this->createUser('admin', Role::Admin);
    $employee = $this->createUser('tomek', Role::Employee, 'BardzoDlugieHaslo1');

    $this->actingAsUser($admin)
        ->post('/admin/users/'.$employee->id.'/toggle')
        ->assertRedirect();

    expect(app(UserRepository::class)->find($employee->id)?->isActive)->toBeFalse();

    auth()->logout();

    $this->from('/login')
        ->post('/login', ['login' => 'tomek', 'password' => 'BardzoDlugieHaslo1'])
        ->assertRedirect('/login');

    $this->assertGuest();
})->with('stores');

it('updates the role and the name of an account', function (string $store) {
    usingStore($this, $store);

    $admin = $this->createUser('admin', Role::Admin);
    $employee = $this->createUser('tomek', Role::Employee);

    $this->actingAsUser($admin)->put('/admin/users/'.$employee->id, [
        'name' => 'Tomasz K.',
        'login' => 'tomek',
        'role' => Role::Technician->value,
        'is_active' => '1',
    ])->assertRedirect();

    $updated = app(UserRepository::class)->find($employee->id);

    expect($updated?->name)->toBe('Tomasz K.')
        ->and($updated?->role)->toBe(Role::Technician);
})->with('stores');

it('does not let an admin block their own account', function (string $store) {
    usingStore($this, $store);

    $admin = $this->createUser('admin', Role::Admin);

    $this->actingAsUser($admin)
        ->post('/admin/users/'.$admin->id.'/toggle')
        ->assertSessionHasErrors('login');

    expect(app(UserRepository::class)->find($admin->id)?->isActive)->toBeTrue();
})->with('stores');

it('gives an employee the release and cost rights in the interface', function (string $store) {
    usingStore($this, $store);

    $employee = $this->createUser('jan', Role::Employee);
    $repo = app(ServiceRepository::class);

    $service = $repo->save(
        $this->acceptService($employee)->with(['status' => ServiceStatus::ReadyForPickup->value])
    );

    $this->actingAsUser($employee)->get('/services/'.$service->id)
        ->assertOk()
        ->assertSee('Wydaj laptopa')
        ->assertSee('Koszt części');
})->with('stores');

it('lets an employee change their own password', function (string $store) {
    usingStore($this, $store);

    $employee = $this->createUser('jan', Role::Employee, 'BardzoDlugieHaslo1');

    $this->actingAsUser($employee)->put('/account/password', [
        'current_password' => 'BardzoDlugieHaslo1',
        'password' => 'NoweBardzoDlugie2',
        'password_confirmation' => 'NoweBardzoDlugie2',
    ])->assertRedirect(route('services.queue'));

    $fresh = app(UserRepository::class)->find($employee->id);

    expect(Hash::check('NoweBardzoDlugie2', (string) $fresh?->password))->toBeTrue();
})->with('stores');

it('forces a password change for an account created by the installer', function (string $store) {
    usingStore($this, $store);

    $user = app(UserRepository::class)->save(UserRecord::fromArray([
        'name' => 'Nowy',
        'login' => 'nowy',
        'password' => Hash::make('BardzoDlugieHaslo1'),
        'role' => Role::Employee->value,
        'is_active' => true,
        'must_change_password' => true,
    ]));

    $this->actingAsUser($user)->get('/')->assertRedirect(route('account.password'));
})->with('stores');

it('swaps the account list in place through one async region', function (string $store) {
    usingStore($this, $store);

    $admin = $this->createUser('admin', Role::Admin);
    $this->createUser('tomek', Role::Employee);
    $this->createUser('ania', Role::Employee, 'TajneHaslo123', false);

    $response = $this->actingAsUser($admin)->get('/admin/users');

    $response->assertOk()
        ->assertSee('data-async-region="admin-users"', false);

    $xpath = adminUsersXpath((string) $response->getContent());
    $regions = $xpath->query('//*[@data-async-region="admin-users"]');

    expect($regions->length)->toBe(1);

    $region = $regions->item(0);

    expect($region)->not->toBeNull()
        ->and($xpath->query('.//table', $region)->length)->toBe(1)
        // A region nested in another one is swapped together with its parent.
        ->and($xpath->query('.//*[@data-async-region]', $region)->length)->toBe(0)
        ->and($xpath->query('ancestor::*[@data-async-region]', $region)->length)->toBe(0);

    $forms = $xpath->query('.//form[contains(@action, "/toggle")]', $region);

    expect($forms->length)->toBe(3);

    $names = [];

    foreach ($forms as $form) {
        expect($form->hasAttribute('data-async-form'))->toBeTrue()
            ->and($form->hasAttribute('data-guard-submit'))->toBeFalse();

        $button = $xpath->query('.//button[@type="submit"]', $form)->item(0);

        expect($button)->not->toBeNull();

        // The visible word is unchanged; the login makes the name unique per row.
        expect(trim($button->textContent))->toBeIn(['Zablokuj', 'Odblokuj']);

        $names[] = trim($button->getAttribute('aria-label'));
    }

    expect($names)->toHaveCount(3)
        ->and(array_unique($names))->toHaveCount(3);

    sort($names);

    expect($names)->toBe(['Odblokuj konto ania', 'Zablokuj konto admin', 'Zablokuj konto tomek']);
})->with('stores');

it('renders the blocked state from the server after a toggle', function (string $store) {
    usingStore($this, $store);

    $admin = $this->createUser('admin', Role::Admin);
    $employee = $this->createUser('tomek', Role::Employee);

    $this->actingAsUser($admin)
        ->post('/admin/users/'.$employee->id.'/toggle')
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $response = $this->actingAsUser($admin)->get('/admin/users');

    $response->assertOk();

    $xpath = adminUsersXpath((string) $response->getContent());
    $row = $xpath->query('//table//tbody/tr[td[2][normalize-space(.)="tomek"]]')->item(0);

    expect($row)->not->toBeNull();

    $badge = $xpath->query('.//span[contains(@class, "badge")]', $row)->item(0);
    $button = $xpath->query('.//form[@data-async-form]//button[@type="submit"]', $row)->item(0);

    expect($badge)->not->toBeNull()
        ->and(trim($badge->textContent))->toBe('zablokowane')
        ->and($button)->not->toBeNull()
        ->and(trim($button->textContent))->toBe('Odblokuj')
        ->and($button->getAttribute('aria-label'))->toBe('Odblokuj konto tomek');
})->with('stores');

it('keeps the account pagination inside the async region', function (string $store) {
    usingStore($this, $store);

    $admin = $this->createUser('admin', Role::Admin);
    $password = Hash::make('TajneHaslo123');

    // 21 accounts is one more than the page size, so the pagination renders. The
    // list never verifies passwords, so one hash serves every filler account.
    foreach (range(1, 20) as $number) {
        app(UserRepository::class)->save(UserRecord::fromArray([
            'name' => 'Konto '.$number,
            'login' => 'konto'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'password' => $password,
            'role' => Role::Employee->value,
            'is_active' => true,
        ]));
    }

    $response = $this->actingAsUser($admin)->get('/admin/users');

    $response->assertOk();

    $xpath = adminUsersXpath((string) $response->getContent());
    $region = $xpath->query('//*[@data-async-region="admin-users"]')->item(0);

    expect($region)->not->toBeNull()
        ->and($xpath->query('.//table', $region)->length)->toBe(1)
        ->and($xpath->query('.//nav[@aria-label="Paginacja"]', $region)->length)->toBe(1);

    $next = $xpath->query('.//nav[@aria-label="Paginacja"]//a[@rel="next"]', $region);

    expect($next->length)->toBe(1)
        ->and($next->item(0)?->getAttribute('href'))->toContain('page=2')
        // The shared pagination component makes the link swap this region in place.
        ->and($next->item(0)?->hasAttribute('data-async-link'))->toBeTrue()
        ->and($next->item(0)?->hasAttribute('data-async-scroll'))->toBeTrue();
})->with('stores');

/**
 * Parses a rendered page so the markup contract is asserted on the DOM — region
 * nesting and per-row attributes cannot be proved with a substring search.
 */
function adminUsersXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);

    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($document);
}
