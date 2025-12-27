<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassificationJob extends Model
{
    use HasUuids;

    protected $fillable = [
        'session_id',
        'status',
        'total_tickets',
        'processed_tickets',
        'results',
        'processing_time_ms',
        'completed_at',
    ];

    protected $casts = [
        'results' => 'array',
        'created_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const UPDATED_AT = null;

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'job_id');
    }
}
