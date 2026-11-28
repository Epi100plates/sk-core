<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Data\ServiceRecord;
use App\Domain\Enums\ServiceStatus;
use DomainException;

/**
 * Business rules that decide whether an operation is allowed at all. Actions
 * call this before writing, so a controller can never bypass the workflow
 * (defect D-18) and the "next stage" button has exactly one meaning (D-46).
 *
 * Release approval (2026-11-27): a laptop may only be handed over from
 * `ready_for_pickup`, i.e. after the owner accepted the release request. The
 * technician cannot skip the acceptance by releasing from the repair stage.
 */
final class ServiceWorkflow
{
    public function canTransition(ServiceStatus $from, ServiceStatus $to): bool
    {
        return $from->canTransitionTo($to);
    }

    /**
     * @throws DomainException
     */
    public function assertTransition(ServiceStatus $from, ServiceStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new DomainException(sprintf(
                'Nie można zmienić statusu z „%s” na „%s”.',
                $from->label(),
                $to->label(),
            ));
        }
    }

    public function nextStatus(ServiceRecord $service): ?ServiceStatus
    {
        return $service->status->nextStatus();
    }

    public function canAdvance(ServiceRecord $service): bool
    {
        $next = $this->nextStatus($service);

        return $next !== null && $this->canTransition($service->status, $next);
    }

    /** Editing is only possible while the service is still open. */
    public function canEdit(ServiceRecord $service): bool
    {
        return $service->status->isActive();
    }

    /**
     * Releasing goes through the owner's acceptance: only a laptop on the
     * "Do wydania" counter can be handed over, exactly like before the change.
     */
    public function canRelease(ServiceRecord $service): bool
    {
        return $service->status === ServiceStatus::ReadyForPickup;
    }

    public function canRequestRelease(ServiceRecord $service): bool
    {
        return $service->status === ServiceStatus::InProgress
            || $service->status === ServiceStatus::AwaitingParts;
    }

    public function canApproveRelease(ServiceRecord $service): bool
    {
        return $service->status === ServiceStatus::AwaitingApproval;
    }

    public function canReturnToQueue(ServiceRecord $service): bool
    {
        return $service->status === ServiceStatus::AwaitingApproval;
    }

    public function canResumeRepair(ServiceRecord $service): bool
    {
        return $service->status === ServiceStatus::WithRemarks;
    }

    public function canCancel(ServiceRecord $service): bool
    {
        return $service->status->isActive();
    }

    public function canUploadPhotos(ServiceRecord $service): bool
    {
        return $service->status->isActive();
    }
}
