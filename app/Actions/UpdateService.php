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
 * Editing an existing order (F-34…F-36).
 *
 * Guarantees that matter for the defects from the audit:
 *  - a partial payload only changes the fields it contains (D-47, `cost`),
 *  - saving without changes writes nothing and creates no audit entry (D-44),
 *  - a status change goes through the workflow and produces `status_changed`,
 *  - `released` can never be set from the edit form (only the release action).
 */
final class UpdateService
{
    use RecordsServiceEvents;

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceEventRepository $events,
        private readonly TransactionManager $transactions,
        private readonly ServiceWorkflow $workflow,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  validated partial payload
     * @return array{service: ServiceRecord, events: int}
     */
    public function handle(
        ServiceRecord $service,
        array $attributes,
        UserRecord $actor,
        AuditContext $context,
    ): array {
        if (! $this->workflow->canEdit($service)) {
            throw new DomainException('Wydanego lub anulowanego zlecenia nie można już edytować.');
        }

        $requestedStatus = null;

        if (array_key_exists('status', $attributes) && $attributes['status'] !== null && $attributes['status'] !== '') {
            $requestedStatus = ServiceStatus::tryFrom((string) $attributes['status'])
                ?? throw new DomainException('Nieznany status zlecenia.');

            if ($requestedStatus === ServiceStatus::Released) {
                throw new DomainException('Wydanie zlecenia odbywa się przez akcję „Wydaj”, nie przez edycję.');
            }

            if ($requestedStatus === ServiceStatus::Cancelled) {
                throw new DomainException('Anulowanie zlecenia odbywa się przez akcję „Anuluj”.');
            }

            if ($requestedStatus !== $service->status) {
                $this->workflow->assertTransition($service->status, $requestedStatus);
            } else {
                $requestedStatus = null;
            }
        }

        $changes = ServiceDetails::changesFromArray($attributes);
        $candidate = $service->with($changes);
        $diff = ServiceDiff::between($service->toArray(), $candidate->toArray());

        if ($diff === [] && ! $requestedStatus instanceof ServiceStatus) {
            // Nothing changed: no write, no event, no shifted deadline (D-44).
            return ['service' => $service, 'events' => 0];
        }

        return $this->transactions->run(function () use ($service, $changes, $diff, $requestedStatus, $actor, $context): array {
            $stored = $diff === [] ? $service : $this->services->save($service->with($changes));
            $eventCount = 0;

            if ($diff !== []) {
                $this->recordEvent(
                    $this->events,
                    $stored->id,
                    ServiceEventType::Updated,
                    $actor,
                    $context,
                    $diff,
                );
                $eventCount++;
            }

            if ($requestedStatus instanceof ServiceStatus) {
                $stored = $this->services->save($stored->with(['status' => $requestedStatus->value]));

                $this->recordEvent(
                    $this->events,
                    $stored->id,
                    ServiceEventType::StatusChanged,
                    $actor,
                    $context,
                    [],
                    $service->status->value,
                    $requestedStatus->value,
                    'Status: '.$service->status->label().' → '.$requestedStatus->label(),
                );
                $eventCount++;
            }

            return ['service' => $stored, 'events' => $eventCount];
        });
    }
}
