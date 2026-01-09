<?php

namespace Tests\Unit\Services\Cache;

use App\Services\Cache\ClassificationCacheRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ClassificationCacheRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ClassificationCacheRepository $cache;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use array cache driver for tests to avoid database cache issues
        config(['cache.default' => 'array']);
        Cache::clear();
        
        $this->cache = new ClassificationCacheRepository();
    }



    public function test_get_cached_returns_null_when_not_found(): void
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test ticket',
            'description' => 'Test description'
        ];

        $result = $this->cache->getCached($ticket);

        $this->assertNull($result);
    }

    public function test_set_cached_and_get_cached_works(): void
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test ticket',
            'description' => 'Test description'
        ];

        $classification = [
            'category' => 'Technical',
            'sentiment' => 'Negative',
            'impact' => 'High',
            'urgency' => 'High',
            'priority' => 'Critical',
            'sla_due_date' => '2025-12-28T20:00:00Z',
            'reasoning' => 'Test reasoning'
        ];

        // Set cache
        $this->cache->setCached($ticket, $classification);

        // Get cache
        $cached = $this->cache->getCached($ticket);

        $this->assertEquals($classification, $cached);
    }

    public function test_cache_expiry_works(): void
    {
        $ticket = [
            'issue_key' => 'TEST-002',
            'summary' => 'Test ticket 2',
            'description' => 'Test description 2'
        ];

        $classification = [
            'category' => 'Technical',
            'sentiment' => 'Negative'
        ];

        // Set cache with very short TTL (1 second)
        $key = $this->generateTestKey($ticket);
        Cache::put($key, $classification, 1); // 1 second TTL

        // Should be available immediately
        $cached = $this->cache->getCached($ticket);
        $this->assertEquals($classification, $cached);

        // Wait for expiry
        sleep(2);

        // Should be null after expiry
        $cached = $this->cache->getCached($ticket);
        $this->assertNull($cached);
    }

    public function test_different_tickets_have_different_cache_keys(): void
    {
        $ticket1 = [
            'issue_key' => 'TEST-001',
            'summary' => 'Ticket 1',
            'description' => 'Description 1'
        ];

        $ticket2 = [
            'issue_key' => 'TEST-002',
            'summary' => 'Ticket 2',
            'description' => 'Description 2'
        ];

        $classification1 = ['category' => 'Technical'];
        $classification2 = ['category' => 'Commercial'];

        // Cache different data for each ticket
        $this->cache->setCached($ticket1, $classification1);
        $this->cache->setCached($ticket2, $classification2);

        // Verify each returns correct data
        $this->assertEquals($classification1, $this->cache->getCached($ticket1));
        $this->assertEquals($classification2, $this->cache->getCached($ticket2));
    }

    public function test_same_ticket_content_generates_same_key(): void
    {
        $ticket1 = [
            'issue_key' => 'TEST-001',
            'summary' => 'Same summary',
            'description' => 'Same description'
        ];

        $ticket2 = [
            'issue_key' => 'TEST-001', // Same
            'summary' => 'Same summary', // Same
            'description' => 'Same description' // Same
        ];

        $classification = ['category' => 'Technical'];

        // Cache with first ticket
        $this->cache->setCached($ticket1, $classification);

        // Should be retrievable with second ticket (same content)
        $cached = $this->cache->getCached($ticket2);
        $this->assertEquals($classification, $cached);
    }

    public function test_invalid_cached_data_is_removed(): void
    {
        $ticket = [
            'issue_key' => 'TEST-003',
            'summary' => 'Test ticket',
            'description' => 'Test description'
        ];

        // Manually set invalid cache data (not an array)
        $key = $this->generateTestKey($ticket);
        Cache::put($key, 'invalid_string_data', 30);

        // getCached should return null and remove invalid data
        $result = $this->cache->getCached($ticket);
        $this->assertNull($result);

        // Verify invalid data was removed
        $this->assertNull(Cache::get($key));
    }

    public function test_missing_ticket_fields_use_empty_strings(): void
    {
        $ticket = [
            // Missing issue_key, summary, description
            'other_field' => 'value'
        ];

        $classification = ['category' => 'Technical'];

        // Should not throw exception
        $this->cache->setCached($ticket, $classification);

        // Should be retrievable
        $cached = $this->cache->getCached($ticket);
        $this->assertEquals($classification, $cached);
    }

    public function test_get_cache_ttl_minutes(): void
    {
        $ttl = $this->cache->getCacheTtlMinutes();

        $this->assertEquals(30, $ttl);
        $this->assertIsInt($ttl);
    }

    public function test_is_cache_available_returns_true_when_working(): void
    {
        $available = $this->cache->isCacheAvailable();

        $this->assertTrue($available);
    }

    public function test_is_cache_available_returns_false_when_broken(): void
    {
        // Mock cache to throw exception
        Cache::shouldReceive('put')->andThrow(new \Exception('Cache broken'));
        Cache::shouldReceive('get')->andThrow(new \Exception('Cache broken'));
        Cache::shouldReceive('forget')->andThrow(new \Exception('Cache broken'));

        $available = $this->cache->isCacheAvailable();

        $this->assertFalse($available);
    }

    public function test_clear_all_returns_zero(): void
    {
        // With database cache driver, clearAll returns 0
        // (pattern-based clearing not supported)
        $cleared = $this->cache->clearAll();

        $this->assertEquals(0, $cleared);
    }

    /**
     * Helper method to generate cache key for testing.
     */
    private function generateTestKey(array $ticket): string
    {
        $issueKey = $ticket['issue_key'] ?? '';
        $summary = $ticket['summary'] ?? '';
        $description = $ticket['description'] ?? '';

        $dataString = $issueKey . '|' . $summary . '|' . $description;
        $hash = hash('sha256', $dataString);

        return 'classification:' . $hash;
    }
}
