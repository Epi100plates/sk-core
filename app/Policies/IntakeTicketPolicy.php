<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Data\IntakeTicketRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Ability;

/**
 * Submissions from the public site form (2026-11-27). Every active account may
 * read and process the inbox; the ability layer keeps the rights explicit and
 * lets a future role lose access without touching the controllers.
 */
final class IntakeTicketPolicy
{
    public function viewAny(UserRecord $user): bool
    {
        return $user->can(Ability::VIEW_INTAKE);
    }

    public function view(UserRecord $user, IntakeTicketRecord $ticket): bool
    {
        return $user->can(Ability::VIEW_INTAKE);
    }

    public function manage(UserRecord $user, IntakeTicketRecord $ticket): bool
    {
        return $user->can(Ability::MANAGE_INTAKE);
    }
}
