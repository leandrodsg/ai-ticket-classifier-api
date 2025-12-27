<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = [
        'job_id',
        'issue_key',
        'summary',
        'description',
        'reporter',
        'category',
        'sentiment',
        'priority',
        'impact',
        'urgency',
        'sla_due_date',
        'reasoning',
    ];

    protected $casts = [
        'sla_due_date' => 'datetime',
        'created_at' => 'datetime',
    ];

    public const UPDATED_AT = null;

    public function job(): BelongsTo
    {
        return $this->belongsTo(ClassificationJob::class, 'job_id');
    }
}
