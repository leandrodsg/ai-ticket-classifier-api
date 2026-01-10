<?php

namespace Tests\Unit\Services\Cache;

use App\Services\Cache\CacheMetricsCollector;
use Tests\TestCase;

class CacheMetricsCollectorTest extends TestCase
{
    private CacheMetricsCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new CacheMetricsCollector();
    }

    public function test_coleta_cache_hits(): void
    {
        // Simular 10 requests, 3 hits
        for ($i = 0; $i < 3; $i++) {
            $this->collector->recordHit();
        }
        for ($i = 0; $i < 7; $i++) {
            $this->collector->recordMiss();
        }

        $metrics = $this->collector->getMetrics();

        $this->assertEquals(3, $metrics['hits']);
        $this->assertEquals(7, $metrics['misses']);
        $this->assertEquals(10, $metrics['total_requests']);
        $this->assertEquals(30.0, $metrics['hit_rate']);
    }

    public function test_coleta_cache_misses(): void
    {
        // Simular 10 requests, 7 misses
        for ($i = 0; $i < 7; $i++) {
            $this->collector->recordMiss();
        }
        for ($i = 0; $i < 3; $i++) {
            $this->collector->recordHit();
        }

        $metrics = $this->collector->getMetrics();

        $this->assertEquals(3, $metrics['hits']);
        $this->assertEquals(7, $metrics['misses']);
        $this->assertEquals(10, $metrics['total_requests']);
        $this->assertEquals(30.0, $metrics['hit_rate']);
    }

    public function test_calcula_hit_rate_percentage(): void
    {
        // Hits: 4, Total: 10
        for ($i = 0; $i < 4; $i++) {
            $this->collector->recordHit();
        }
        for ($i = 0; $i < 6; $i++) {
            $this->collector->recordMiss();
        }

        $metrics = $this->collector->getMetrics();

        $this->assertEquals(40.0, $metrics['hit_rate']);
    }

    public function test_metricas_resetam_corretamente(): void
    {
        // Coletar métricas
        $this->collector->recordHit();
        $this->collector->recordMiss();

        $metrics = $this->collector->getMetrics();
        $this->assertEquals(1, $metrics['hits']);
        $this->assertEquals(1, $metrics['misses']);

        // Resetar
        $this->collector->reset();

        $metricsAfterReset = $this->collector->getMetrics();
        $this->assertEquals(0, $metricsAfterReset['hits']);
        $this->assertEquals(0, $metricsAfterReset['misses']);
        $this->assertEquals(0, $metricsAfterReset['total_requests']);
        $this->assertEquals(0.0, $metricsAfterReset['hit_rate']);
    }
}