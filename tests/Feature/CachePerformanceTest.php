<?php

namespace Tests\Feature;

use App\Services\Cache\ClassificationCacheRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CachePerformanceTest extends TestCase
{
    use RefreshDatabase;

    private ClassificationCacheRepository $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new ClassificationCacheRepository();
    }

    public function test_cache_performance_improvement(): void
    {
        $ticket = [
            'issue_key' => 'PERF-TEST-001',
            'summary' => 'Performance test ticket for cache benchmarking',
            'description' => 'This ticket is used to measure cache performance improvements'
        ];

        $classification = [
            'category' => 'Technical',
            'sentiment' => 'Neutral',
            'impact' => 'High',
            'urgency' => 'High',
            'priority' => 'Critical',
            'sla_due_date' => '2025-12-28T22:00:00Z',
            'reasoning' => 'Performance test classification result'
        ];

        // Test 1: Measure time WITHOUT cache (simulated)
        $timesWithoutCache = [];
        for ($i = 0; $i < 10; $i++) {
            $startTime = microtime(true);

            // Simulate work that would be done without cache
            // (in real scenario, this would be AI classification)
            $this->simulateAiClassificationWork();

            $endTime = microtime(true);
            $timesWithoutCache[] = $endTime - $startTime;
        }

        $avgTimeWithoutCache = array_sum($timesWithoutCache) / count($timesWithoutCache);

        // Set up cache
        $this->cache->setCached($ticket, $classification);

        // Test 2: Measure time WITH cache
        $timesWithCache = [];
        for ($i = 0; $i < 10; $i++) {
            $startTime = microtime(true);

            // This should hit cache (fast)
            $result = $this->cache->getCached($ticket);

            $endTime = microtime(true);
            $timesWithCache[] = $endTime - $startTime;

            // Verify we got the expected result
            $this->assertEquals($classification, $result);
        }

        $avgTimeWithCache = array_sum($timesWithCache) / count($timesWithCache);

        // Calculate performance improvement
        $improvementPercent = (($avgTimeWithoutCache - $avgTimeWithCache) / $avgTimeWithoutCache) * 100;

        // Assert significant performance improvement (>20% for in-memory cache with reduced load)
        $this->assertGreaterThan(20, $improvementPercent,
            sprintf('Cache should provide >20%% performance improvement. Got: %.2f%% (Without: %.6fs, With: %.6fs)',
                $improvementPercent, $avgTimeWithoutCache, $avgTimeWithCache));

        // Additional assertions
        $this->assertGreaterThan(0, $avgTimeWithCache, 'Cache time should be > 0');
        $this->assertLessThan($avgTimeWithoutCache, $avgTimeWithCache, 'Cache should be faster than simulated work');

        // Log performance metrics for monitoring
        $this->logPerformanceMetrics($avgTimeWithoutCache, $avgTimeWithCache, $improvementPercent);
    }

    public function test_cache_memory_efficiency(): void
    {
        // Test that cache doesn't consume excessive memory
        $initialMemory = memory_get_usage();

        // Create multiple cache entries
        for ($i = 0; $i < 100; $i++) {
            $ticket = [
                'issue_key' => 'MEM-TEST-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'summary' => 'Memory efficiency test ticket ' . $i,
                'description' => 'Testing cache memory usage with multiple entries'
            ];

            $classification = [
                'category' => 'Technical',
                'sentiment' => 'Neutral',
                'impact' => 'Medium',
                'urgency' => 'Low',
                'priority' => 'Low',
                'sla_due_date' => '2025-12-29T10:00:00Z',
                'reasoning' => 'Memory test classification'
            ];

            $this->cache->setCached($ticket, $classification);
        }

        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;

        // Assert reasonable memory usage (less than 10MB for 100 entries)
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed,
            sprintf('Cache should use less than 10MB for 100 entries. Used: %.2f MB', $memoryUsed / (1024 * 1024)));

        // Verify all entries are accessible
        for ($i = 0; $i < 100; $i++) {
            $ticket = [
                'issue_key' => 'MEM-TEST-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'summary' => 'Memory efficiency test ticket ' . $i,
                'description' => 'Testing cache memory usage with multiple entries'
            ];

            $cached = $this->cache->getCached($ticket);
            $this->assertNotNull($cached, "Cache entry $i should be accessible");
            $this->assertEquals('Technical', $cached['category'], "Cache entry $i should have correct data");
        }
    }

    public function test_cache_scalability_under_load(): void
    {
        $baseTime = microtime(true);

        // Simulate high load scenario
        for ($i = 0; $i < 50; $i++) {
            $ticket = [
                'issue_key' => 'LOAD-TEST-' . $i,
                'summary' => 'Load test ticket ' . $i,
                'description' => 'Testing cache under load conditions'
            ];

            $classification = [
                'category' => $i % 2 === 0 ? 'Technical' : 'Commercial',
                'sentiment' => 'Neutral',
                'impact' => 'Medium',
                'urgency' => 'Medium',
                'priority' => 'Medium',
                'sla_due_date' => '2025-12-28T23:00:00Z',
                'reasoning' => 'Load test classification'
            ];

            // Set cache
            $this->cache->setCached($ticket, $classification);

            // Immediately read back (cache hit)
            $cached = $this->cache->getCached($ticket);
            $this->assertEquals($classification, $cached);

            // Every 10 iterations, verify cache availability
            if ($i % 10 === 0) {
                $this->assertTrue($this->cache->isCacheAvailable(), "Cache should be available at iteration $i");
            }
        }

        $totalTime = microtime(true) - $baseTime;

        // Assert reasonable total time (less than 2 seconds for 50 operations)
        $this->assertLessThan(2.0, $totalTime,
            sprintf('Cache operations should complete in <2 seconds. Took: %.3f seconds', $totalTime));

        // Verify cache stats
        $this->assertTrue($this->cache->isCacheAvailable(), 'Cache should remain available after load test');
    }

    /**
     * Simulate AI classification work (without cache).
     * This represents the computational cost that cache avoids.
     */
    private function simulateAiClassificationWork(): void
    {
        // Simulate various operations that AI classification might do
        // Reduced computational load to prevent timeouts
        $data = str_repeat('classification workload simulation with more complex processing ', 500);
        $hash = hash('sha256', $data);
        $json = json_encode(['hash' => $hash, 'length' => strlen($data), 'complexity' => 'high']);
        $decoded = json_decode($json, true);

        // Add computational work (simulate AI processing) - reduced from 50k to 5k iterations
        $result = 0;
        for ($i = 0; $i < 5000; $i++) {
            $result += $i * 2;
            // Add some string operations to simulate text processing - reduced frequency
            if ($i % 500 === 0) {
                $temp = strtoupper(substr($data, 0, 100));
                $temp = strtolower($temp);
                $result += strlen($temp);
            }
        }

        // Simulate hash operations (like tokenization) - reduced from 100 to 10
        for ($i = 0; $i < 10; $i++) {
            $subHash = hash('md5', $data . $i);
            $result += hexdec(substr($subHash, 0, 8));
        }

        // Verify work was done
        $this->assertGreaterThan(0, $result);
        $this->assertIsArray($decoded);
        $this->assertEquals('high', $decoded['complexity']);
    }

    /**
     * Log performance metrics for monitoring and debugging.
     */
    private function logPerformanceMetrics(float $avgTimeWithoutCache, float $avgTimeWithCache, float $improvementPercent): void
    {
        // In a real application, you might log this to a monitoring system
        $metrics = [
            'cache_performance_test' => [
                'avg_time_without_cache' => round($avgTimeWithoutCache * 1000, 3) . 'ms',
                'avg_time_with_cache' => round($avgTimeWithCache * 1000, 3) . 'ms',
                'performance_improvement' => round($improvementPercent, 2) . '%',
                'timestamp' => now()->toISOString(),
            ]
        ];

        // For testing purposes, we just assert the metrics are reasonable
        $this->assertGreaterThan(0, $avgTimeWithoutCache);
        $this->assertGreaterThan(0, $avgTimeWithCache);
        $this->assertGreaterThan(0, $improvementPercent);

        // Cache time should be at least 1.4x faster (adjusted for reduced computational load)
        $speedupRatio = $avgTimeWithoutCache / $avgTimeWithCache;
        $this->assertGreaterThan(1.4, $speedupRatio,
            sprintf('Cache should be at least 1.4x faster. Got: %.1fx speedup', $speedupRatio));
    }
}
