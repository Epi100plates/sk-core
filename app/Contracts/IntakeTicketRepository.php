<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Domain\Data\IntakeQuery;
use App\Domain\Data\IntakeTicketRecord;
use App\Domain\Data\Page;

/**
 * Storage contract for submissions from the public site form (2026-11-27).
 * JSON and MySQL implementations must behave exactly the same; `number` is
 * unique and doubles as the idempotency key for the HMAC endpoint.
 */
interface IntakeTicketRepository
{
    public function find(int $id): ?IntakeTicketRecord;

    public function findByNumber(string $number): ?IntakeTicketRecord;

    public function save(IntakeTicketRecord $ticket): IntakeTicketRecord;

    /** @return Page<IntakeTicketRecord> */
    public function paginate(IntakeQuery $query): Page;

    /** @return list<IntakeTicketRecord> */
    public function all(): array;

    /** @return array<string, int> status value => count */
    public function countsByStatus(): array;

    public function count(): int;
}
