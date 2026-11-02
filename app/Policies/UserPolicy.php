<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Data\UserRecord;
use App\Domain\Enums\Ability;

final class UserPolicy
{
    public function viewAny(UserRecord $user): bool
    {
        return $user->can(Ability::MANAGE_USERS);
    }

    public function create(UserRecord $user): bool
    {
        return $user->can(Ability::MANAGE_USERS);
    }

    public function update(UserRecord $user, UserRecord $target): bool
    {
        return $user->can(Ability::MANAGE_USERS);
    }
}
