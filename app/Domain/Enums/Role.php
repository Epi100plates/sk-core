<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Three roles, one permission matrix (decision D9 / feature F-06).
 *
 * D9: the employee has the full operational job — accepting, editing, changing
 * status, photos, searching, releasing a laptop and changing price/cost.
 * The technician has exactly the same rights today; the separation exists so
 * future features can grant the technician more without touching the views.
 */
enum Role: string
{
    case Admin = 'admin';
    case Technician = 'technician';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Technician => 'Technik',
            self::Employee => 'Pracownik',
        };
    }

    /** @return array<string, bool> */
    public function abilities(): array
    {
        return match ($this) {
            self::Admin => [
                Ability::VIEW_SERVICES => true,
                Ability::CREATE_SERVICE => true,
                Ability::UPDATE_SERVICE => true,
                Ability::ADVANCE_STATUS => true,
                Ability::RELEASE_SERVICE => true,
                Ability::CANCEL_SERVICE => true,
                Ability::MANAGE_PHOTOS => true,
                Ability::MANAGE_USERS => true,
            ],
            self::Technician, self::Employee => [
                Ability::VIEW_SERVICES => true,
                Ability::CREATE_SERVICE => true,
                Ability::UPDATE_SERVICE => true,
                Ability::ADVANCE_STATUS => true,
                Ability::RELEASE_SERVICE => true,
                Ability::CANCEL_SERVICE => true,
                Ability::MANAGE_PHOTOS => true,
                Ability::MANAGE_USERS => false,
            ],
        };
    }

    public function can(string $ability): bool
    {
        return $this->abilities()[$ability] ?? false;
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
