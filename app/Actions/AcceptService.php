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
use App\Domain\FieldLabels;
use App\Domain\ServiceDiff;
use App\Domain\ValueObjects\Phone;
use App\Support\Audit\AuditContext;
use Carbon\CarbonImmutable;

/**
 * Accepting a laptop (F-07…F-20).
 *
 * One atomic unit: the service row, its `accepted` audit event and the
 * customer facing reference are created together or not at all (defect D-17).
 */
final class AcceptService
{
    use RecordsServiceEvents;

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceEventRepository $events,
        private readonly TransactionManager $transactions,
    ) {}

    public function handle(ServiceDetails $details, UserRecord $actor, AuditContext $context): ServiceRecord
    {
        return $this->transactions->run(function () use ($details, $actor, $context): ServiceRecord {
            $now = CarbonImmutable::now('UTC');
            $year = (int) $now->timezone(FieldLabels::displayTimezone())->format('Y');

            $service = ServiceRecord::fromArray([
                'id' => 0,
                'reference' => $this->services->nextReference($year),
                'phone' => $details->phone,
                'phone_normalized' => Phone::normalize($details->phone),
                'device_model' => $details->deviceModel,
                'serial_number' => $details->serialNumber,
                'issue' => $details->issue,
                'device_password' => $details->devicePassword,
                'notes' => $details->notes,
                'is_express' => $details->isExpress,
                'planned_at' => $details->plannedAt->format('Y-m-d H:i:s'),
                'price' => $details->price,
                'cost' => $details->cost,
                'seal_number' => $details->sealNumber,
                'status' => ServiceStatus::Pending->value,
                'accepted_by' => $actor->id,
                'accepted_at' => $now->format('Y-m-d H:i:s'),
                'version' => 0,
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);

            $stored = $this->services->save($service);

            $this->recordEvent(
                $this->events,
                $stored->id,
                ServiceEventType::Accepted,
                $actor,
                $context,
                ServiceDiff::snapshot($stored->toArray()),
                null,
                ServiceStatus::Pending->value,
                'Przyjęto zlecenie '.$stored->reference,
            );

            return $stored;
        });
    }
}
