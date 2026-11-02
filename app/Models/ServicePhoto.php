<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePhoto extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'service_photos';

    /** @var list<string> */
    protected $fillable = [
        'service_id',
        'disk',
        'path',
        'thumb_path',
        'mime',
        'size',
        'width',
        'height',
        'checksum',
        'uploaded_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'service_id' => 'integer',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'uploaded_by' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
