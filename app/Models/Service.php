<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent model for the `mysql` data store (section 6.1).
 *
 * @property string $device_password
 */
class Service extends Model
{
    use SoftDeletes;

    protected $table = 'services';

    /** @var list<string> */
    protected $fillable = [
        'legacy_id',
        'reference',
        'phone',
        'phone_normalized',
        'device_model',
        'serial_number',
        'issue',
        'device_password',
        'notes',
        'approval_remarks',
        'is_express',
        'planned_at',
        'price',
        'cost',
        'seal_number',
        'status',
        'accepted_by',
        'accepted_at',
        'confirmation_issued_at',
        'released_at',
        'released_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'client_status_url',
        'client_status_synced_at',
        'version',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'is_express' => 'boolean',
            'planned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'confirmation_issued_at' => 'datetime',
            'released_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'client_status_synced_at' => 'datetime',
            'deleted_at' => 'datetime',
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'device_password' => 'encrypted',
            'version' => 'integer',
        ];
    }

    /** @return HasMany<ServicePhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(ServicePhoto::class);
    }

    /** @return HasMany<ServiceEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ServiceEvent::class);
    }
}
