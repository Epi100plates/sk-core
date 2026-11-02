<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Data\ServicePhotoRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Ability;

final class ServicePhotoPolicy
{
    public function view(UserRecord $user, ServicePhotoRecord $photo): bool
    {
        return $user->can(Ability::VIEW_SERVICES);
    }

    public function delete(UserRecord $user, ServicePhotoRecord $photo): bool
    {
        return $user->can(Ability::MANAGE_PHOTOS);
    }
}
