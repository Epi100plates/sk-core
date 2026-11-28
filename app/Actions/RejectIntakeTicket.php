<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\IntakeTicketRepository;
use App\Domain\Data\IntakeTicketRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\IntakeStatus;
use Carbon\CarbonImmutable;
use DomainException;

/**
 * "Odrzuć": the workshop decided not to accept the submission (2026-11-27).
 * A short reason is mandatory and stays on the record, so nothing disappears
 * without a trace.
 */
final class RejectIntakeTicket
{
    public function __construct(private readonly IntakeTicketRepository $tickets) {}

    public function handle(IntakeTicketRecord $ticket, string $reason, UserRecord $actor): IntakeTicketRecord
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('Podaj powód odrzucenia.');
        }

        if ($ticket->status === IntakeStatus::Rejected) {
            return $ticket;
        }

        if ($ticket->status === IntakeStatus::Converted) {
            throw new DomainException('Zgłoszenie zostało już przyjęte do serwisu.');
        }

        return $this->tickets->save($ticket->with([
            'status' => IntakeStatus::Rejected->value,
            'rejection_reason' => $reason,
            'rejected_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'),
            'rejected_by' => $actor->id,
        ]));
    }
}
