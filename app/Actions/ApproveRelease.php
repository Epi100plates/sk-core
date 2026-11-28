<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\ServiceEventRepository;
use App\Contracts\ServiceRepository;
use App\Contracts\TransactionManager;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Ability;
use App\Domain\Enums\ServiceEventType;
use App\Domain\Enums\ServiceStatus;
use App\Domain\ServiceDiff;
use App\Domain\ServiceWorkflow;
use App\Support\Audit\AuditContext;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The owner accepts a release request: `awaiting_approval` → `ready_for_pickup`.
 *
 * Only the role with {@see Ability::APPROVE_RELEASE} (Szef) may do it, and the
 * check is repeated here so a controller cannot be the only gate. The previous
 * remarks are cleared — they were addressed by handing the laptop to the release
 * counter.
 */
final class ApproveRelease
{
    use RecordsServiceEvents;

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceEventRepository $events,
        private readonly TransactionManager $transactions,
        private readonly ServiceWorkflow $workflow,
    ) {}

    public function handle(ServiceRecord $service, UserRecord $actor, AuditContext $context): ServiceRecord
    {
        if (! $actor->can(Ability::APPROVE_RELEASE)) {
            throw new AuthorizationException('Tylko szef może zatwierdzić wydanie.');
        }

        if (! $this->workflow->canApproveRelease($service)) {
            throw new DomainException('To zlecenie nie czeka na akceptację wydania.');
        }

        $this->workflow->assertTransition($service->status, ServiceStatus::ReadyForPickup);

        return $this->transactions->run(function () use ($service, $actor, $context): ServiceRecord {
            $from = $service->status;
            $candidate = $service->with([
                'status' => ServiceStatus::ReadyForPickup->value,
                'approval_remarks' => null,
            ]);
            $diff = ServiceDiff::between($service->toArray(), $candidate->toArray());

            $stored = $this->services->save($candidate);

            $this->recordEvent(
                $this->events,
                $stored->id,
                ServiceEventType::ReleaseApproved,
                $actor,
                $context,
                $diff,
                $from->value,
                ServiceStatus::ReadyForPickup->value,
                'Wydanie zatwierdzone — laptop na stanowisku „Do wydania”.',
            );

            return $stored;
        });
    }
}
