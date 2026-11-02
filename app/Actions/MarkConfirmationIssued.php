<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\ServiceEventRepository;
use App\Contracts\ServiceRepository;
use App\Contracts\TransactionManager;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\ServiceEventType;
use App\Support\Audit\AuditContext;
use Carbon\CarbonImmutable;

/**
 * Marks "potwierdzenie przyjęcia wydane" (F-17, decision D6).
 *
 * The timestamp records when the customer actually received the confirmation,
 * so printing again does not move it. Repeated printing still works; it simply
 * does not create a second audit entry for an unchanged value.
 */
final class MarkConfirmationIssued
{
    use RecordsServiceEvents;

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceEventRepository $events,
        private readonly TransactionManager $transactions,
    ) {}

    public function handle(ServiceRecord $service, UserRecord $actor, AuditContext $context): ServiceRecord
    {
        if ($service->hasIssuedConfirmation()) {
            return $service;
        }

        return $this->transactions->run(function () use ($service, $actor, $context): ServiceRecord {
            $issuedAt = CarbonImmutable::now('UTC');
            $stored = $this->services->save($service->with([
                'confirmation_issued_at' => $issuedAt->format('Y-m-d H:i:s'),
            ]));

            $this->recordEvent(
                $this->events,
                $stored->id,
                ServiceEventType::Updated,
                $actor,
                $context,
                ['confirmation_issued_at' => ['from' => null, 'to' => $stored->confirmationIssuedAt?->format('Y-m-d H:i:s')]],
                null,
                null,
                'Wydrukowano potwierdzenie przyjęcia',
            );

            return $stored;
        });
    }
}
