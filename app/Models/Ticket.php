<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Ticket extends Model
{
    use HasFactory;

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

    public function getIssueKeyAttribute($value): string
    {
        return strtoupper($value);
    }

    public function setIssueKeyAttribute($value): void
    {
        $this->attributes['issue_key'] = strtoupper($value);
    }

    public function getSummaryAttribute($value): string
    {
        return trim($value);
    }

    public function setSummaryAttribute($value): void
    {
        $this->attributes['summary'] = ucfirst(trim($value));
    }

    public function getDescriptionAttribute($value): string
    {
        return trim($value);
    }

    public function setDescriptionAttribute($value): void
    {
        $cleaned = strip_tags($value);
        $this->attributes['description'] = trim($cleaned);
    }

    public function getReporterAttribute($value): string
    {
        return strtolower(trim($value));
    }

    public function setReporterAttribute($value): void
    {
        $email = strtolower(trim($value));
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->attributes['reporter'] = $email;
        } else {
            throw new \InvalidArgumentException('Invalid email format: ' . $email);
        }
    }

    public function getCategoryAttribute($value): string
    {
        $validCategories = ['Technical', 'Commercial', 'Billing', 'General', 'Support'];
        return in_array($value, $validCategories) ? $value : 'General';
    }

    public function setCategoryAttribute($value): void
    {
        $validCategories = ['technical', 'commercial', 'billing', 'general', 'support'];
        $normalized = strtolower(trim($value));
        
        if (in_array($normalized, $validCategories)) {
            $this->attributes['category'] = ucfirst($normalized);
        } else {
            $this->attributes['category'] = 'General';
        }
    }

    public function getSentimentAttribute($value): string
    {
        $validSentiments = ['Positive', 'Negative', 'Neutral'];
        return in_array($value, $validSentiments) ? $value : 'Neutral';
    }

    public function setSentimentAttribute($value): void
    {
        $validSentiments = ['positive', 'negative', 'neutral'];
        $normalized = strtolower(trim($value));
        
        if (in_array($normalized, $validSentiments)) {
            $this->attributes['sentiment'] = ucfirst($normalized);
        } else {
            $this->attributes['sentiment'] = 'Neutral';
        }
    }

    public function getPriorityAttribute($value): string
    {
        $validPriorities = ['Critical', 'High', 'Medium', 'Low'];
        return in_array($value, $validPriorities) ? $value : 'Medium';
    }

    public function setPriorityAttribute($value): void
    {
        $validPriorities = ['critical', 'high', 'medium', 'low'];
        $normalized = strtolower(trim($value));
        
        if (in_array($normalized, $validPriorities)) {
            $this->attributes['priority'] = ucfirst($normalized);
        } else {
            $this->attributes['priority'] = 'Medium';
        }
    }

    public function getImpactAttribute($value): string
    {
        $validImpacts = ['High', 'Medium', 'Low'];
        return in_array($value, $validImpacts) ? $value : 'Medium';
    }

    public function setImpactAttribute($value): void
    {
        $validImpacts = ['high', 'medium', 'low'];
        $normalized = strtolower(trim($value));
        
        if (in_array($normalized, $validImpacts)) {
            $this->attributes['impact'] = ucfirst($normalized);
        } else {
            $this->attributes['impact'] = 'Medium';
        }
    }

    public function getUrgencyAttribute($value): string
    {
        $validUrgencies = ['High', 'Medium', 'Low'];
        return in_array($value, $validUrgencies) ? $value : 'Medium';
    }

    public function setUrgencyAttribute($value): void
    {
        $validUrgencies = ['high', 'medium', 'low'];
        $normalized = strtolower(trim($value));
        
        if (in_array($normalized, $validUrgencies)) {
            $this->attributes['urgency'] = ucfirst($normalized);
        } else {
            $this->attributes['urgency'] = 'Medium';
        }
    }

    public function getReasoningAttribute($value): string
    {
        return trim($value);
    }

    public function setReasoningAttribute($value): void
    {
        $this->attributes['reasoning'] = trim($value);
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->sla_due_date) {
            return false;
        }
        
        return Carbon::now()->isAfter($this->sla_due_date);
    }

    public function getSlaTimeRemainingAttribute(): ?string
    {
        if (!$this->sla_due_date) {
            return null;
        }
        
        $now = Carbon::now();
        $due = Carbon::parse($this->sla_due_date);
        
        if ($now->isAfter($due)) {
            return 'Overdue';
        }
        
        return $now->diffForHumans($due, true) . ' remaining';
    }
}
