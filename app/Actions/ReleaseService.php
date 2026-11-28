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
use App\Domain\ServiceDiff;
use App\Domain\ServiceWorkflow;
use App\Support\Audit\AuditContext;
use DomainException;

/**
 * Handing the laptop back to the customer (F-39…F-42).
 *
 * One contract for phone and desktop — the old mobile form sent `service_id`
 * while the backend expected `id`, so releasing from a phone always failed
 * (defect D-10). The release date comes from the form (D-11/D-56) and a final
 * service cannot be released twice (D-46).
 */
final class ReleaseService
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
        ReleaseDetails $details,
        UserRecord $actor,
        AuditContext $context,
    ): ServiceRecord {
        if (! $this->workflow->canRelease($service)) {
            throw new DomainException('Wydać można tylko zlecenie na etapie „Do wydania”. Najpierw szef musi zatwierdzić wydanie.');
        }

        if ($details->releasedAt->isFuture()) {
            throw new DomainException('Data wydania nie może być z przyszłości.');
        }

        return $this->transactions->run(function () use ($service, $details, $actor, $context): ServiceRecord {
            $from = $service->status;

            $changes = [
                'status' => ServiceStatus::Released->value,
                'released_at' => $details->releasedAt->format('Y-m-d H:i:s'),
                'released_by' => $actor->id,
                'price' => $details->price,
                'cost' => $details->cost,
                'notes' => $details->notes,
                'seal_number' => $details->sealNumber,
                'device_password' => $details->devicePassword,
                'serial_number' => $details->serialNumber,
            ];

            $candidate = $service->with($changes);
            $diff = ServiceDiff::between($service->toArray(), $candidate->toArray());

            $stored = $this->services->save($candidate);

            $this->recordEvent(
                $this->events,
                $stored->id,
                ServiceEventType::Released,
                $actor,
                $context,
                $diff,
                $from->value,
                ServiceStatus::Released->value,
                'Wydano klientowi. Cena: '.$stored->priceMoney()->format(),
            );

            return $stored;
        });
    }
}
