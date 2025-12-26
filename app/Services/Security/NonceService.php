<?php

namespace App\Services\Security;

use App\Models\UsedNonce;
use Illuminate\Support\Str;
use Carbon\Carbon;

class NonceService
{
    /**
     * Generate a new nonce
     */
    public function generate(): string
    {
        return Str::random(32);
    }

    /**
     * Validate nonce (check if not used before)
     */
    public function validate(string $nonce): bool
    {
        // Check if nonce exists in database
        $exists = UsedNonce::where('nonce', $nonce)->exists();

        if ($exists) {
            return false; // Replay attack detected
        }

        // Mark as used with 1 hour expiration
        $this->markAsUsed($nonce, Carbon::now()->addHour());

        return true;
    }

    /**
     * Mark nonce as used
     */
    public function markAsUsed(string $nonce, Carbon $expiresAt): void
    {
        UsedNonce::create([
            'nonce' => $nonce,
            'used_at' => Carbon::now(),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Clean up expired nonces
     */
    public function cleanup(): int
    {
        return UsedNonce::where('expires_at', '<', Carbon::now())->delete();
    }

    /**
     * Check if nonce is expired (for cleanup purposes)
     */
    public function isExpired(string $nonce): bool
    {
        $usedNonce = UsedNonce::where('nonce', $nonce)->first();

        if (!$usedNonce) {
            return false; // Nonce not found, can't be expired
        }

        return $usedNonce->expires_at->isPast();
    }
}
