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
