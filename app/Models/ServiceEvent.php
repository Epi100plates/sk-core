<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit entry. Immutable by design: the model has no `updated_at` and the
 * repository never updates an existing row.
 */
class ServiceEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'service_events';

    /** @var list<string> */
    protected $fillable = [
        'service_id',
        'type',
        'actor_id',
        'from_status',
        'to_status',
        'changes',
        'description',
        'ip',
        'user_agent',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'service_id' => 'integer',
            'actor_id' => 'integer',
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
