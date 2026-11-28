<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\IntakeTicketRepository;
use App\Domain\Data\IntakeTicketRecord;
use App\Domain\Data\ServiceRecord;
use App\Domain\Enums\IntakeStatus;
use Carbon\CarbonImmutable;
use DomainException;

/**
 * "Przyjmij do serwisu": the submission became a repair order (2026-11-27).
 *
 * The acceptance form is opened prefilled from the ticket; once the order exists
 * this action links both records, so the inbox always shows where a submission
 * went and the detail screen links straight to the service order.
 */
final class ConvertIntakeTicket
{
    public function __construct(private readonly IntakeTicketRepository $tickets) {}

    public function handle(IntakeTicketRecord $ticket, ServiceRecord $service): IntakeTicketRecord
    {
        if ($ticket->status === IntakeStatus::Converted) {
            return $ticket;
        }

        if ($ticket->status === IntakeStatus::Rejected) {
            throw new DomainException('Odrzuconego zgłoszenia nie można przyjąć do serwisu.');
        }

        return $this->tickets->save($ticket->with([
            'status' => IntakeStatus::Converted->value,
            'converted_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:s'),
            'converted_service_id' => $service->id,
            'converted_service_reference' => $service->reference,
        ]));
    }
}
