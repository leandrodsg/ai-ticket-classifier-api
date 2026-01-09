<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimitDetector
{
    private const CACHE_KEY_CONCURRENCY = 'ai_concurrency_level';
    private const CACHE_KEY_LAST_429 = 'ai_last_429_timestamp';
    private const CACHE_KEY_REQUEST_COUNT = 'ai_request_count_minute';
    private const CACHE_TTL_MINUTES = 5;

    private int $currentConcurrency;
    private int $maxConcurrency;

    public function __construct()
    {
        $this->maxConcurrency = config('ai.concurrency.concurrent_requests', 4);
        $this->currentConcurrency = Cache::get(self::CACHE_KEY_CONCURRENCY, $this->maxConcurrency);
    }

    /**
     * Detecta se estamos em rate limiting baseado em resposta 429 ou contagem de requests
     */
    public function isRateLimited(): bool
    {
        // Verificar se teve 429 recentemente
        $last429 = Cache::get(self::CACHE_KEY_LAST_429);
        if ($last429 && now()->diffInSeconds($last429) < 60) {
            return true;
        }

        // Verificar RPM (requests per minute)
        $requestCount = Cache::get(self::CACHE_KEY_REQUEST_COUNT, 0);
        $rpmLimit = config('ai.rate_limiting.rpm_limit', 20);

        if ($requestCount >= $rpmLimit) {
            Log::warning('Rate limit detected by request count', [
                'current_count' => $requestCount,
                'rpm_limit' => $rpmLimit
            ]);
            return true;
        }

        return false;
    }

    /**
     * Registra ocorrência de rate limiting (429)
     */
    public function recordRateLimit(): void
    {
        Cache::put(self::CACHE_KEY_LAST_429, now(), self::CACHE_TTL_MINUTES);
        Log::warning('Rate limit 429 recorded, reducing concurrency');
        $this->reduceConcurrency();
    }

    /**
     * Registra uma request bem-sucedida
     */
    public function recordSuccessfulRequest(): void
    {
        $count = Cache::get(self::CACHE_KEY_REQUEST_COUNT, 0) + 1;
        Cache::put(self::CACHE_KEY_REQUEST_COUNT, $count, 1); // 1 minute TTL
    }

    /**
     * Reduz a concorrência após rate limiting
     */
    public function reduceConcurrency(): void
    {
        $newConcurrency = max(1, $this->currentConcurrency - 1);
        $this->currentConcurrency = $newConcurrency;
        Cache::put(self::CACHE_KEY_CONCURRENCY, $newConcurrency, self::CACHE_TTL_MINUTES);

        Log::info('Concurrency reduced', [
            'from' => $this->currentConcurrency + 1,
            'to' => $newConcurrency
        ]);
    }

    /**
     * Aumenta gradualmente a concorrência se não há problemas
     */
    public function increaseConcurrency(): void
    {
        if ($this->currentConcurrency < $this->maxConcurrency) {
            $this->currentConcurrency++;
            Cache::put(self::CACHE_KEY_CONCURRENCY, $this->currentConcurrency, self::CACHE_TTL_MINUTES);

            Log::info('Concurrency increased', [
                'to' => $this->currentConcurrency
            ]);
        }
    }

    /**
     * Retorna delay recomendado entre batches em ms
     */
    public function getDelayMs(): int
    {
        $last429 = Cache::get(self::CACHE_KEY_LAST_429);

        if ($last429) {
            $secondsSince429 = now()->diffInSeconds($last429);
            if ($secondsSince429 < 60) {
                // Delay exponencial baseado no tempo desde o último 429
                return min(5000, 1000 * (60 - $secondsSince429));
            }
        }

        return config('ai.rate_limiting.delay_between_batches_ms', 0);
    }

    /**
     * Retorna a concorrência atual
     */
    public function getCurrentConcurrency(): int
    {
        return $this->currentConcurrency;
    }

    /**
     * Reseta o detector (usado em testes ou recovery)
     */
    public function reset(): void
    {
        $this->currentConcurrency = $this->maxConcurrency;
        Cache::forget(self::CACHE_KEY_CONCURRENCY);
        Cache::forget(self::CACHE_KEY_LAST_429);
        Cache::forget(self::CACHE_KEY_REQUEST_COUNT);
    }
}