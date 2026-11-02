<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\ServiceEventRepository;
use App\Contracts\ServiceRepository;
use App\Contracts\TransactionManager;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\ServiceEventType;
use App\Domain\Enums\ServiceStatus;
use App\Domain\ServiceWorkflow;
use App\Support\Audit\AuditContext;
use DomainException;
use InvalidArgumentException;

/**
 * Cancelling an order (feature F-56, defect D-43).
 *
 * The reception needs a reason, the service leaves the active queue and stays in
 * the archive with its audit trail. An already released order cannot be cancelled.
 */
final class CancelService
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
        string $reason,
        UserRecord $actor,
        AuditContext $context,
    ): ServiceRecord {
        if (! $this->workflow->canCancel($service)) {
            throw new DomainException('Wydanego lub anulowanego zlecenia nie można anulować.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Podaj powód anulowania.');
        }

        return $this->transactions->run(function () use ($service, $reason, $actor, $context): ServiceRecord {
            $from = $service->status;
            $stored = $this->services->save($service->with([
                'status' => ServiceStatus::Cancelled->value,
                'cancelled_at' => now()->utc()->format('Y-m-d H:i:s'),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ]));

            $this->recordEvent(
                $this->events,
                $stored->id,
                ServiceEventType::Cancelled,
                $actor,
                $context,
                ['cancellation_reason' => ['from' => null, 'to' => $reason]],
                $from->value,
                ServiceStatus::Cancelled->value,
                'Anulowano: '.$reason,
            );

            return $stored;
        });
    }
}
