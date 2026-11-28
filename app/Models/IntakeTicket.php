<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the `intake_tickets` table (MySQL store, 2026-11-27).
 * Application code talks to `Contracts\IntakeTicketRepository` and
 * `Domain\Data\IntakeTicketRecord` instead.
 */
class IntakeTicket extends Model
{
    protected $table = 'intake_tickets';

    /** @var list<string> */
    protected $fillable = [
        'number',
        'name',
        'phone',
        'device',
        'problem',
        'source_page',
        'submitted_at',
        'status',
        'rejection_reason',
        'contacted_at',
        'rejected_at',
        'rejected_by',
        'converted_at',
        'converted_service_id',
        'converted_service_reference',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'contacted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'converted_at' => 'datetime',
            'rejected_by' => 'integer',
            'converted_service_id' => 'integer',
        ];
    }
}
