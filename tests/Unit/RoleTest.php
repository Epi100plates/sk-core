<?php

declare(strict_types=1);

use App\Domain\Enums\Ability;
use App\Domain\Enums\Role;

/*
|--------------------------------------------------------------------------
| Role matrix (decision D9 / feature F-06)
|--------------------------------------------------------------------------
*/

it('lets an employee release laptops and change the cost', function () {
    $employee = Role::Employee;

    expect($employee->can(Ability::RELEASE_SERVICE))->toBeTrue()
        ->and($employee->can(Ability::UPDATE_SERVICE))->toBeTrue()
        ->and($employee->can(Ability::CANCEL_SERVICE))->toBeTrue()
        ->and($employee->can(Ability::CREATE_SERVICE))->toBeTrue()
        ->and($employee->can(Ability::MANAGE_PHOTOS))->toBeTrue();
});

it('gives the technician the same operational rights as the employee', function () {
    foreach (Ability::all() as $ability) {
        expect(Role::Technician->can($ability))->toBe(Role::Employee->can($ability));
    }
});

it('reserves account management for the admin', function () {
    expect(Role::Admin->can(Ability::MANAGE_USERS))->toBeTrue()
        ->and(Role::Employee->can(Ability::MANAGE_USERS))->toBeFalse()
        ->and(Role::Technician->can(Ability::MANAGE_USERS))->toBeFalse();
});

it('labels all three roles in Polish', function () {
    expect(Role::Admin->label())->toBe('Administrator')
        ->and(Role::Technician->label())->toBe('Technik')
        ->and(Role::Employee->label())->toBe('Pracownik');
});
