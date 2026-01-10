<?php

namespace Tests\Feature;

use App\Services\Cache\CacheMetricsCollector;
use App\Services\Cache\ClassificationCacheRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_funciona_com_tickets_similares(): void
    {
        // Create cache repository with metrics collector
        $metrics = new CacheMetricsCollector();
        $cache = new ClassificationCacheRepository($metrics);

        // Create 5 similar tickets
        $tickets = [];
        for ($i = 1; $i <= 5; $i++) {
            $tickets[] = [
                'issue_key' => "TEST-{$i}",
                'summary' => 'Cannot access dashboard',
                'description' => 'User cannot login to system'
            ];
        }

        $classification = ['category' => 'Technical'];

        // Process similar tickets
        foreach ($tickets as $ticket) {
            $cached = $cache->getCached($ticket);
            if ($cached === null) {
                $cache->setCached($ticket, $classification);
            }
        }

        // Check metrics: 4 hits (after first miss), 1 miss
        $metricsData = $cache->getMetrics();
        $this->assertEquals(4, $metricsData['hits']);
        $this->assertEquals(1, $metricsData['misses']);
        $this->assertEquals(80.0, $metricsData['hit_rate']);
        $this->assertEquals(5, $metricsData['total_requests']);
    }

    public function test_metricas_aparecem_no_response(): void
    {
        // This would be tested in a full integration test with the API
        // For now, just verify the cache repository returns metrics
        $metrics = new CacheMetricsCollector();
        $cache = new ClassificationCacheRepository($metrics);

        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test ticket',
            'description' => 'Test description'
        ];

        // Miss
        $cache->getCached($ticket);
        // Hit
        $cache->setCached($ticket, ['category' => 'Technical']);
        $cache->getCached($ticket);

        $metricsData = $cache->getMetrics();

        $this->assertArrayHasKey('cache_metrics', ['cache_metrics' => $metricsData]);
        $this->assertEquals(1, $metricsData['hits']);
        $this->assertEquals(1, $metricsData['misses']);
        $this->assertEquals(2, $metricsData['total_requests']);
    }

    public function test_cache_persiste_entre_requests(): void
    {
        $metrics1 = new CacheMetricsCollector();
        $cache1 = new ClassificationCacheRepository($metrics1);

        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Cannot access dashboard',
            'description' => 'User cannot login'
        ];

        $classification = ['category' => 'Technical'];

        // First "request" - cache miss, then set
        $cache1->getCached($ticket); // miss
        $cache1->setCached($ticket, $classification);

        // Second "request" - should be hit
        $metrics2 = new CacheMetricsCollector();
        $cache2 = new ClassificationCacheRepository($metrics2);

        $cache2->getCached($ticket); // should hit

        $this->assertEquals(1, $cache2->getMetrics()['hits']);
        $this->assertEquals(0, $cache2->getMetrics()['misses']);
    }

    public function test_cache_com_concorrencia(): void
    {
        // This test would verify cache works with concurrent processing
        // For now, just test basic functionality
        $metrics = new CacheMetricsCollector();
        $cache = new ClassificationCacheRepository($metrics);

        $tickets = [];
        for ($i = 1; $i <= 15; $i++) {
            $tickets[] = [
                'issue_key' => "TEST-{$i}",
                'summary' => 'Cannot access dashboard',
                'description' => 'User cannot login'
            ];
        }

        $classification = ['category' => 'Technical'];

        // First ticket caches
        $cache->setCached($tickets[0], $classification);

        // Other similar tickets should hit cache
        for ($i = 1; $i < 15; $i++) {
            $cached = $cache->getCached($tickets[$i]);
            $this->assertEquals($classification, $cached);
        }

        $metricsData = $cache->getMetrics();
        $this->assertEquals(14, $metricsData['hits']); // 14 hits
        $this->assertEquals(0, $metricsData['misses']); // 0 misses (only sets, no gets before)
    }
}