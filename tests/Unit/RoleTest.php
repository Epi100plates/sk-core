<?php

declare(strict_types=1);

use App\Domain\Enums\Ability;
use App\Domain\Enums\Role;

/*
|--------------------------------------------------------------------------
| Role matrix (decision D9 / feature F-06, release approval 2026-11-27)
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

it('reserves account management for the owner and the serwisant admin', function () {
    expect(Role::Owner->can(Ability::MANAGE_USERS))->toBeTrue()
        ->and(Role::Admin->can(Ability::MANAGE_USERS))->toBeTrue()
        ->and(Role::Employee->can(Ability::MANAGE_USERS))->toBeFalse()
        ->and(Role::Technician->can(Ability::MANAGE_USERS))->toBeFalse();
});

it('lets only the owner accept a release request', function () {
    expect(Role::Owner->can(Ability::APPROVE_RELEASE))->toBeTrue()
        ->and(Role::Owner->approvesRelease())->toBeTrue()
        ->and(Role::Admin->can(Ability::APPROVE_RELEASE))->toBeFalse()
        ->and(Role::Admin->approvesRelease())->toBeFalse()
        ->and(Role::Technician->can(Ability::APPROVE_RELEASE))->toBeFalse()
        ->and(Role::Employee->can(Ability::APPROVE_RELEASE))->toBeFalse();
});

it('lets the serwisant admin keep the full workshop rights', function () {
    foreach ([
        Ability::VIEW_SERVICES,
        Ability::CREATE_SERVICE,
        Ability::UPDATE_SERVICE,
        Ability::ADVANCE_STATUS,
        Ability::RELEASE_SERVICE,
        Ability::CANCEL_SERVICE,
        Ability::MANAGE_PHOTOS,
        Ability::VIEW_INTAKE,
        Ability::MANAGE_INTAKE,
    ] as $ability) {
        expect(Role::Admin->can($ability))->toBeTrue();
    }
});

it('labels every role in Polish', function () {
    expect(Role::Owner->label())->toBe('Szef')
        ->and(Role::Admin->label())->toBe('Serwisant admin')
        ->and(Role::Technician->label())->toBe('Technik')
        ->and(Role::Employee->label())->toBe('Pracownik');
});
