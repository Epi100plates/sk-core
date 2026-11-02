<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\ServiceEventRepository;
use App\Domain\Data\ServiceEventRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\ServiceEventType;
use App\Support\Audit\AuditContext;
use Carbon\CarbonImmutable;

/**
 * Shared audit writing for all actions. One event per real change, with the
 * actor and the request context (defect D-54: no duplicated log lines).
 */
trait RecordsServiceEvents
{
    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    protected function recordEvent(
        ServiceEventRepository $events,
        int $serviceId,
        ServiceEventType $type,
        ?UserRecord $actor,
        AuditContext $context,
        array $changes = [],
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $description = null,
    ): ServiceEventRecord {
        return $events->append(new ServiceEventRecord(
            id: 0,
            serviceId: $serviceId,
            type: $type,
            actorId: $actor?->id,
            fromStatus: $fromStatus,
            toStatus: $toStatus,
            changes: $changes,
            description: $description,
            ip: $context->ip,
            userAgent: $context->userAgent,
            createdAt: CarbonImmutable::now('UTC'),
        ));
    }
}
