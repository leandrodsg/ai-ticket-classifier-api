<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ClassificationCacheRepository
{
    private const CACHE_TTL_MINUTES = 30;
    private const CACHE_KEY_PREFIX = 'classification';

    /**
     * Get cached classification for a ticket.
     *
     * @param array $ticket Ticket data with issue_key, summary, description
     * @return array|null Cached classification or null if not found
     */
    public function getCached(array $ticket): ?array
    {
        $key = $this->generateCacheKey($ticket);

        $cached = Cache::get($key);

        if ($cached === null) {
            Log::info('Cache miss', [
                'ticket' => $ticket['issue_key'] ?? 'unknown',
                'cache_key' => $key,
            ]);
            return null;
        }

        // Ensure cached data is valid array
        if (!is_array($cached)) {
            Log::warning('Invalid cache data', [
                'ticket' => $ticket['issue_key'] ?? 'unknown',
                'cache_key' => $key,
            ]);
            // Remove invalid cache entry
            Cache::forget($key);
            return null;
        }

        Log::info('Cache hit', [
            'ticket' => $ticket['issue_key'] ?? 'unknown',
            'cache_key' => $key,
        ]);

        return $cached;
    }

    /**
     * Cache classification result for a ticket.
     *
     * @param array $ticket Ticket data with issue_key, summary, description
     * @param array $classification Classification result to cache
     */
    public function setCached(array $ticket, array $classification): void
    {
        $key = $this->generateCacheKey($ticket);

        Cache::put($key, $classification, now()->addMinutes(self::CACHE_TTL_MINUTES));

        Log::info('Cache set', [
            'ticket' => $ticket['issue_key'] ?? 'unknown',
            'cache_key' => $key,
            'ttl_minutes' => self::CACHE_TTL_MINUTES,
        ]);
    }

    /**
     * Generate cache key for ticket classification.
     *
     * Key format: classification:{SHA256_HASH}
     * Hash includes: issue_key + summary + description
     */
    private function generateCacheKey(array $ticket): string
    {
        // Extract relevant fields for cache key
        $issueKey = $ticket['issue_key'] ?? '';
        $summary = $ticket['summary'] ?? '';
        $description = $ticket['description'] ?? '';

        // Create deterministic string for hashing
        $dataString = $issueKey . '|' . $summary . '|' . $description;

        // Generate SHA256 hash for consistent key
        $hash = hash('sha256', $dataString);

        return self::CACHE_KEY_PREFIX . ':' . $hash;
    }

    /**
     * Clear all classification cache entries.
     * Useful for maintenance or when cache becomes stale.
     */
    public function clearAll(): int
    {
        // Note: Laravel's database cache driver doesn't support
        // pattern-based clearing, so we can't easily clear all
        // classification entries. This would require a custom
        // implementation or switching to Redis for production.

        // For now, return 0 as placeholder
        // In production with Redis, we could use:
        // $keys = Cache::store('redis')->keys('classification:*');
        // return Cache::store('redis')->del($keys);

        return 0;
    }

    /**
     * Get cache TTL in minutes.
     */
    public function getCacheTtlMinutes(): int
    {
        return self::CACHE_TTL_MINUTES;
    }

    /**
     * Check if cache is available and working.
     */
    public function isCacheAvailable(): bool
    {
        try {
            $testKey = 'cache_test_' . time();
            Cache::put($testKey, 'test_value', 1);
            $result = Cache::get($testKey);
            Cache::forget($testKey);

            return $result === 'test_value';
        } catch (\Exception $e) {
            return false;
        }
    }
}
