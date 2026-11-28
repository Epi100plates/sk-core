<?php

declare(strict_types=1);

namespace App\Policies;

use App\Actions\ApproveRelease;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Ability;
use App\Domain\ServiceWorkflow;

/**
 * Everything that can be done with a service order, in one place. Controllers
 * call `$this->authorize(...)` and Blade uses `@can(...)`; there is no raw
 * `if ($user->role === ...)` inside a view.
 */
final class ServicePolicy
{
    public function __construct(private readonly ServiceWorkflow $workflow) {}

    public function view(UserRecord $user, ServiceRecord $service): bool
    {
        return $user->can(Ability::VIEW_SERVICES);
    }

    public function create(UserRecord $user): bool
    {
        return $user->can(Ability::CREATE_SERVICE);
    }

    public function update(UserRecord $user, ServiceRecord $service): bool
    {
        return $user->can(Ability::UPDATE_SERVICE) && $this->workflow->canEdit($service);
    }

    public function advance(UserRecord $user, ServiceRecord $service): bool
    {
        // Only the role is checked here: whether this service has a next stage is
        // a workflow question, answered with a readable 422 by AdvanceServiceStatus
        // (so a forced request never looks like a permission error).
        return $user->can(Ability::ADVANCE_STATUS);
    }

    public function release(UserRecord $user, ServiceRecord $service): bool
    {
        // Like `advance`: role right here, state rules in the action (422). The
        // screen hides the button unless the workflow says the laptop is ready.
        return $user->can(Ability::RELEASE_SERVICE);
    }

    /**
     * The owner accepts a requested release. Enforced both here (403 on the
     * routes) and in {@see ApproveRelease} so no controller can be
     * the only gate.
     */
    public function approveRelease(UserRecord $user, ServiceRecord $service): bool
    {
        return $user->can(Ability::APPROVE_RELEASE) && $this->workflow->canApproveRelease($service);
    }

    /** The owner sends the laptop back with remarks. */
    public function returnToQueue(UserRecord $user, ServiceRecord $service): bool
    {
        return $user->can(Ability::APPROVE_RELEASE) && $this->workflow->canReturnToQueue($service);
    }

    public function cancel(UserRecord $user, ServiceRecord $service): bool
    {
        return $user->can(Ability::CANCEL_SERVICE) && $this->workflow->canCancel($service);
    }

    public function managePhotos(UserRecord $user, ServiceRecord $service): bool
    {
        return $user->can(Ability::MANAGE_PHOTOS) && $this->workflow->canUploadPhotos($service);
    }

    public function print(UserRecord $user, ServiceRecord $service): bool
    {
        return $user->can(Ability::VIEW_SERVICES);
    }
}
