<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Role model (decision D9 / feature F-06, extended 2026-11-27).
 *
 * Owner ("Szef") - the person who runs the workshop. Full operational rights and
 * the only role that can accept or return a release request.
 * Admin ("Serwisant admin") - a full workshop administrator: everything the owner
 * does operationally, account management included, but a technician cannot accept
 * their own release request, so the final "Potwierdź" stays with the owner.
 * Technician/Employee - the same operational job as before; the technician stays
 * separate so future features can grant it more without touching the views.
 *
 * Existing `admin` accounts keep working: the value is unchanged, only the label
 * and the new approval ability are different. Give one account the `owner` role
 * (Users screen or `php artisan users:owner <login>`) to run the approval queue.
 */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Technician = 'technician';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Szef',
            self::Admin => 'Serwisant admin',
            self::Technician => 'Technik',
            self::Employee => 'Pracownik',
        };
    }

    /** @return array<string, bool> */
    public function abilities(): array
    {
        return match ($this) {
            self::Owner => [
                Ability::VIEW_SERVICES => true,
                Ability::CREATE_SERVICE => true,
                Ability::UPDATE_SERVICE => true,
                Ability::ADVANCE_STATUS => true,
                Ability::RELEASE_SERVICE => true,
                Ability::APPROVE_RELEASE => true,
                Ability::CANCEL_SERVICE => true,
                Ability::MANAGE_PHOTOS => true,
                Ability::MANAGE_USERS => true,
                Ability::VIEW_INTAKE => true,
                Ability::MANAGE_INTAKE => true,
            ],
            self::Admin => [
                Ability::VIEW_SERVICES => true,
                Ability::CREATE_SERVICE => true,
                Ability::UPDATE_SERVICE => true,
                Ability::ADVANCE_STATUS => true,
                Ability::RELEASE_SERVICE => true,
                Ability::APPROVE_RELEASE => false,
                Ability::CANCEL_SERVICE => true,
                Ability::MANAGE_PHOTOS => true,
                Ability::MANAGE_USERS => true,
                Ability::VIEW_INTAKE => true,
                Ability::MANAGE_INTAKE => true,
            ],
            self::Technician, self::Employee => [
                Ability::VIEW_SERVICES => true,
                Ability::CREATE_SERVICE => true,
                Ability::UPDATE_SERVICE => true,
                Ability::ADVANCE_STATUS => true,
                Ability::RELEASE_SERVICE => true,
                Ability::APPROVE_RELEASE => false,
                Ability::CANCEL_SERVICE => true,
                Ability::MANAGE_PHOTOS => true,
                Ability::MANAGE_USERS => false,
                Ability::VIEW_INTAKE => true,
                Ability::MANAGE_INTAKE => true,
            ],
        };
    }

    public function can(string $ability): bool
    {
        return $this->abilities()[$ability] ?? false;
    }

    /** True only for the role that accepts release requests. */
    public function approvesRelease(): bool
    {
        return $this->can(Ability::APPROVE_RELEASE);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
