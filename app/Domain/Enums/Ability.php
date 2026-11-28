<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Named abilities used by Gate checks, controllers and Blade. Keeping them in
 * one place means granting a new right is a single line in {@see Role}.
 */
final class Ability
{
    public const VIEW_SERVICES = 'services.view';

    public const CREATE_SERVICE = 'services.create';

    public const UPDATE_SERVICE = 'services.update';

    public const ADVANCE_STATUS = 'services.advance';

    public const RELEASE_SERVICE = 'services.release';

    /** Owner only: accept or return a release request (2026-11-27). */
    public const APPROVE_RELEASE = 'services.approve_release';

    public const CANCEL_SERVICE = 'services.cancel';

    public const MANAGE_PHOTOS = 'services.photos';

    public const MANAGE_USERS = 'users.manage';

    public const VIEW_INTAKE = 'intake.view';

    public const MANAGE_INTAKE = 'intake.manage';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::VIEW_SERVICES,
            self::CREATE_SERVICE,
            self::UPDATE_SERVICE,
            self::ADVANCE_STATUS,
            self::RELEASE_SERVICE,
            self::APPROVE_RELEASE,
            self::CANCEL_SERVICE,
            self::MANAGE_PHOTOS,
            self::MANAGE_USERS,
            self::VIEW_INTAKE,
            self::MANAGE_INTAKE,
        ];
    }

    private function __construct() {}
}
