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
     * Uses database UNIQUE constraint to prevent race conditions
     */
    public function validate(string $nonce): bool
    {
        try {
            // Try to insert nonce (will fail if already exists due to UNIQUE constraint)
            $this->markAsUsed($nonce, Carbon::now()->addHour());
            return true; // First time using this nonce
        } catch (\Illuminate\Database\QueryException $e) {
            // SQLSTATE[23000] = UNIQUE constraint violation
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE constraint')) {
                return false; // Nonce already used (replay attack detected)
            }
            // Re-throw unexpected database errors
            throw $e;
        }
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
