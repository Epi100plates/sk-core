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

/**
 * "Następny etap" button (F-24, F-33).
 *
 * The status can only move along the workflow; a released or cancelled service
 * has no next step at all (defect D-46: dead "Next" button on released orders).
 */
final class AdvanceServiceStatus
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
        $next = $this->workflow->nextStatus($service);

        if (! $next instanceof ServiceStatus) {
            throw new DomainException('To zlecenie nie ma już następnego etapu.');
        }

        $this->workflow->assertTransition($service->status, $next);

        return $this->transactions->run(function () use ($service, $next, $actor, $context): ServiceRecord {
            $from = $service->status;
            $stored = $this->services->save($service->with(['status' => $next->value]));

            $this->recordEvent(
                $this->events,
                $stored->id,
                ServiceEventType::StatusChanged,
                $actor,
                $context,
                [],
                $from->value,
                $next->value,
                'Status: '.$from->label().' → '.$next->label(),
            );

            return $stored;
        });
    }
}
