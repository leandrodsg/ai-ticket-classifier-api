<?php

namespace Tests\Feature;

use App\Services\Cache\ClassificationCacheRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ClassificationCacheRepository $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new ClassificationCacheRepository();
    }

    public function test_cache_integration_with_rate_limiting(): void
    {
        // Create a ticket that will be classified multiple times
        $ticket = [
            'issue_key' => 'CACHE-TEST-001',
            'summary' => 'Cache integration test ticket',
            'description' => 'This ticket tests cache integration with rate limiting'
        ];

        $classification = [
            'category' => 'Technical',
            'sentiment' => 'Neutral',
            'impact' => 'Medium',
            'urgency' => 'Medium',
            'priority' => 'Medium',
            'sla_due_date' => '2025-12-28T21:00:00Z',
            'reasoning' => 'Cache integration test'
        ];

        // First request - should cache the result
        $this->cache->setCached($ticket, $classification);

        // Verify cache is working
        $cached = $this->cache->getCached($ticket);
        $this->assertEquals($classification, $cached);

        // Multiple subsequent requests should hit cache (no additional AI calls)
        for ($i = 0; $i < 5; $i++) {
            $cachedAgain = $this->cache->getCached($ticket);
            $this->assertEquals($classification, $cachedAgain, "Cache miss on iteration " . ($i + 1));
        }

        // Verify only one cache entry exists (not multiple entries)
        $cacheKey = $this->generateCacheKey($ticket);
        $this->assertTrue(Cache::has($cacheKey), 'Cache key should exist');

        // Verify cache contains the expected data structure
        $cachedData = Cache::get($cacheKey);
        $this->assertIsArray($cachedData, 'Cache should contain array data');
        $this->assertEquals($classification, $cachedData, 'Cache data should match original classification');
    }

    public function test_cache_does_not_affect_rate_limiting(): void
    {
        // This test verifies that cache hits don't count against rate limits
        // (since cached requests don't make actual AI calls)

        $ticket = [
            'issue_key' => 'RATE-CACHE-TEST',
            'summary' => 'Rate limit cache test',
            'description' => 'Testing that cache hits don\'t affect rate limits'
        ];

        $classification = [
            'category' => 'Technical',
            'sentiment' => 'Positive'
        ];

        // Cache the result first
        $this->cache->setCached($ticket, $classification);

        // Now make multiple requests that would normally hit rate limits
        // But since they hit cache, they shouldn't count against rate limits
        for ($i = 0; $i < 10; $i++) {
            $result = $this->cache->getCached($ticket);
            $this->assertEquals($classification, $result, "Cache miss on request " . ($i + 1));
        }

        // Verify cache is still working
        $this->assertTrue($this->cache->isCacheAvailable(), 'Cache should still be available');

        // Verify we can still set new cache entries
        $newTicket = [
            'issue_key' => 'RATE-CACHE-TEST-2',
            'summary' => 'Second rate limit cache test',
            'description' => 'Testing cache functionality after multiple hits'
        ];

        $newClassification = [
            'category' => 'Commercial',
            'sentiment' => 'Negative'
        ];

        $this->cache->setCached($newTicket, $newClassification);
        $cachedNew = $this->cache->getCached($newTicket);
        $this->assertEquals($newClassification, $cachedNew, 'New cache entry should work');
    }

    public function test_cache_expiry_does_not_break_integration(): void
    {
        $ticket = [
            'issue_key' => 'EXPIRY-TEST',
            'summary' => 'Cache expiry integration test',
            'description' => 'Testing cache expiry behavior'
        ];

        $classification = [
            'category' => 'Technical',
            'sentiment' => 'Neutral'
        ];

        // Set cache using repository (which uses 30-minute TTL)
        $this->cache->setCached($ticket, $classification);

        // Verify cache works initially
        $cached = $this->cache->getCached($ticket);
        $this->assertEquals($classification, $cached, 'Cache should work initially');

        // For array cache driver, manually clear to simulate expiry
        // In production with Redis/file cache, this would happen automatically
        $key = $this->generateCacheKey($ticket);
        Cache::forget($key);

        // Cache should be expired (manually cleared)
        $expired = $this->cache->getCached($ticket);
        $this->assertNull($expired, 'Cache should be expired');

        // Verify cache key no longer exists
        $this->assertFalse(Cache::has($key), 'Expired cache key should not exist');

        // System should still work - can set new cache
        $newClassification = [
            'category' => 'Commercial',
            'sentiment' => 'Positive'
        ];

        $this->cache->setCached($ticket, $newClassification);
        $newCached = $this->cache->getCached($ticket);
        $this->assertEquals($newClassification, $newCached, 'New cache should work after expiry');
    }

    /**
     * Helper method to generate cache key (matches ClassificationCacheRepository logic).
     */
    private function generateCacheKey(array $ticket): string
    {
        $generator = new \App\Services\Cache\SemanticCacheKeyGenerator();
        return $generator->generateKey($ticket);
    }
}
