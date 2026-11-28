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
 * The owner returns a release request to the repair queue with remarks:
 * `awaiting_approval` → `with_remarks`.
 *
 * The remarks are stored on the order (so the technician sees them in the queue
 * and on the card) and are also written into the audit history.
 */
final class ReturnServiceToQueue
{
    use RecordsServiceEvents;

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceEventRepository $events,
        private readonly TransactionManager $transactions,
        private readonly ServiceWorkflow $workflow,
    ) {}

    public function handle(
        ServiceRecord $service,
        string $remarks,
        UserRecord $actor,
        AuditContext $context,
    ): ServiceRecord {
        if (! $actor->can(Ability::APPROVE_RELEASE)) {
            throw new AuthorizationException('Tylko szef może zwrócić zlecenie do kolejki.');
        }

        if (! $this->workflow->canReturnToQueue($service)) {
            throw new DomainException('Tylko zlecenie oczekujące na akceptację można zwrócić do kolejki.');
        }

        $remarks = trim($remarks);

        if ($remarks === '') {
            throw new DomainException('Podaj uwagi do poprawy.');
        }

        $this->workflow->assertTransition($service->status, ServiceStatus::WithRemarks);

        return $this->transactions->run(function () use ($service, $remarks, $actor, $context): ServiceRecord {
            $from = $service->status;
            $candidate = $service->with([
                'status' => ServiceStatus::WithRemarks->value,
                'approval_remarks' => $remarks,
            ]);
            $diff = ServiceDiff::between($service->toArray(), $candidate->toArray());

            $stored = $this->services->save($candidate);

            $this->recordEvent(
                $this->events,
                $stored->id,
                ServiceEventType::ReturnedWithRemarks,
                $actor,
                $context,
                $diff,
                $from->value,
                ServiceStatus::WithRemarks->value,
                'Zwrócono do poprawy: '.$remarks,
            );

            return $stored;
        });
    }
}
