<?php

namespace App\Services\Cache;

class CacheMetricsCollector
{
    private int $hits = 0;
    private int $misses = 0;

    /**
     * Record a cache hit.
     */
    public function recordHit(): void
    {
        $this->hits++;
    }

    /**
     * Record a cache miss.
     */
    public function recordMiss(): void
    {
        $this->misses++;
    }

    /**
     * Get current metrics.
     */
    public function getMetrics(): array
    {
        $total = $this->hits + $this->misses;

        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'total_requests' => $total,
            'hit_rate' => $total > 0 ? round(($this->hits / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * Reset all metrics.
     */
    public function reset(): void
    {
        $this->hits = 0;
        $this->misses = 0;
    }

    /**
     * Get hit count.
     */
    public function getHits(): int
    {
        return $this->hits;
    }

    /**
     * Get miss count.
     */
    public function getMisses(): int
    {
        return $this->misses;
    }

    /**
     * Get total requests.
     */
    public function getTotalRequests(): int
    {
        return $this->hits + $this->misses;
    }

    /**
     * Get hit rate percentage.
     */
    public function getHitRate(): float
    {
        $total = $this->getTotalRequests();
        return $total > 0 ? round(($this->hits / $total) * 100, 1) : 0.0;
    }
}