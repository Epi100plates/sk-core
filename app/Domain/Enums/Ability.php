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

    public const CANCEL_SERVICE = 'services.cancel';

    public const MANAGE_PHOTOS = 'services.photos';

    public const MANAGE_USERS = 'users.manage';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::VIEW_SERVICES,
            self::CREATE_SERVICE,
            self::UPDATE_SERVICE,
            self::ADVANCE_STATUS,
            self::RELEASE_SERVICE,
            self::CANCEL_SERVICE,
            self::MANAGE_PHOTOS,
            self::MANAGE_USERS,
        ];
    }

    private function __construct() {}
}
