<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\IntakeTicketRepository;
use App\Domain\Data\IntakeTicketRecord;
use App\Domain\Enums\IntakeStatus;
use Carbon\CarbonImmutable;
use DomainException;

/**
 * "Kontakt": the workshop reached the customer (2026-11-27). Calling it twice is
 * harmless; a converted or rejected submission cannot be re-opened.
 */
final class ContactIntakeTicket
{
    public function __construct(private readonly IntakeTicketRepository $tickets) {}

    public function handle(IntakeTicketRecord $ticket): IntakeTicketRecord
    {
        if ($ticket->status === IntakeStatus::Contacted) {
            return $ticket;
        }

        if ($ticket->status->isFinal()) {
            throw new DomainException('Zgłoszenie jest już zamknięte.');
        }

        return $this->tickets->save($ticket->with([
            'status' => IntakeStatus::Contacted->value,
            'contacted_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'),
        ]));
    }
}
