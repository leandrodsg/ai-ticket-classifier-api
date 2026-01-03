<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassificationJob extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'session_id',
        'status',
        'total_tickets',
        'processed_tickets',
        'results',
        'error_message',
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

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
