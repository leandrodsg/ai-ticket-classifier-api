<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UsedNonce extends Model
{
    use HasFactory;

    public $timestamps = false; // Disable automatic timestamps

    protected $fillable = [
        'nonce',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Scope for expired nonces
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', Carbon::now());
    }

    /**
     * Check if nonce is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
