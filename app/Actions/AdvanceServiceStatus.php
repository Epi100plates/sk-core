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
 * The one "next stage" button (F-24, F-33).
 *
 * The status can only move along the workflow; a released or cancelled service
 * has no next step at all (defect D-46: dead "Next" button on released orders).
 *
 * Two transitions carry their own audit event so the history reads the release
 * approval flow instead of a generic "status changed" (owner request 2026-11-27):
 *  - in_progress/awaiting_parts → awaiting_approval: "Zgłoszono do wydania",
 *  - with_remarks → in_progress: "Wznowiono naprawę".
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
                $this->eventType($from, $next),
                $actor,
                $context,
                [],
                $from->value,
                $next->value,
                $this->description($from, $next),
            );

            return $stored;
        });
    }

    private function eventType(ServiceStatus $from, ServiceStatus $to): ServiceEventType
    {
        return match (true) {
            $to === ServiceStatus::AwaitingApproval => ServiceEventType::ReleaseRequested,
            $from === ServiceStatus::WithRemarks && $to === ServiceStatus::InProgress => ServiceEventType::ResumedRepair,
            default => ServiceEventType::StatusChanged,
        };
    }

    private function description(ServiceStatus $from, ServiceStatus $to): string
    {
        return match (true) {
            $to === ServiceStatus::AwaitingApproval => 'Zgłoszono gotowość do wydania — czeka na akceptację szefa.',
            $from === ServiceStatus::WithRemarks && $to === ServiceStatus::InProgress => 'Wznowiono naprawę po uwagach szefa.',
            default => 'Status: '.$from->label().' → '.$to->label(),
        };
    }
}
