<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\RateLimitDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RateLimitDetectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_detecta_rate_limiting_429()
    {
        // Arrange
        $detector = new RateLimitDetector();

        // Act - Registrar 429
        $detector->recordRateLimit();

        // Assert - Deve estar em rate limit
        $this->assertTrue($detector->isRateLimited());
    }

    public function test_ajusta_concorrencia_apos_429()
    {
        // Arrange
        config(['ai.concurrency.concurrent_requests' => 4]);
        $detector = new RateLimitDetector();
        $initialConcurrency = $detector->getCurrentConcurrency();

        // Act - Registrar 429 (deve reduzir concorrência)
        $detector->recordRateLimit();
        $newConcurrency = $detector->getCurrentConcurrency();

        // Assert
        $this->assertEquals(4, $initialConcurrency);
        $this->assertEquals(3, $newConcurrency); // Reduzido de 4 para 3
    }

    public function test_aumenta_concorrencia_gradualmente()
    {
        // Arrange
        config(['ai.concurrency.concurrent_requests' => 4]);
        $detector = new RateLimitDetector();
        
        // Reduzir primeiro
        $detector->recordRateLimit();
        $this->assertEquals(3, $detector->getCurrentConcurrency());

        // Act - Aumentar gradualmente
        $detector->increaseConcurrency();

        // Assert
        $this->assertEquals(4, $detector->getCurrentConcurrency());
    }

    public function test_nao_ultrapassa_max_concurrency()
    {
        // Arrange
        config(['ai.concurrency.concurrent_requests' => 4]);
        $detector = new RateLimitDetector();
        
        $this->assertEquals(4, $detector->getCurrentConcurrency());

        // Act - Tentar aumentar além do máximo
        $detector->increaseConcurrency();
        $detector->increaseConcurrency();

        // Assert - Não deve ultrapassar 4
        $this->assertEquals(4, $detector->getCurrentConcurrency());
    }

    public function test_calcula_rpm_corretamente()
    {
        // Arrange
        config(['ai.rate_limiting.rpm_limit' => 20]);
        $detector = new RateLimitDetector();

        // Act - Registrar 15 requests
        for ($i = 0; $i < 15; $i++) {
            $detector->recordSuccessfulRequest();
        }

        // Assert - Não deve estar em rate limit (15 < 20)
        $this->assertFalse($detector->isRateLimited());

        // Act - Registrar mais 6 requests (total 21)
        for ($i = 0; $i < 6; $i++) {
            $detector->recordSuccessfulRequest();
        }

        // Assert - Deve estar em rate limit (21 > 20)
        $this->assertTrue($detector->isRateLimited());
    }

    public function test_delay_aumenta_apos_rate_limit()
    {
        // Arrange
        $detector = new RateLimitDetector();
        $initialDelay = $detector->getDelayMs();

        // Act - Registrar rate limit
        $detector->recordRateLimit();
        $newDelay = $detector->getDelayMs();

        // Assert - Delay deve aumentar
        $this->assertGreaterThan($initialDelay, $newDelay);
        $this->assertGreaterThan(0, $newDelay);
    }

    public function test_concurrency_minima_eh_1()
    {
        // Arrange
        config(['ai.concurrency.concurrent_requests' => 2]);
        $detector = new RateLimitDetector();

        // Act - Reduzir múltiplas vezes
        $detector->recordRateLimit(); // 2 -> 1
        $detector->recordRateLimit(); // 1 -> 1 (não deve ir abaixo de 1)
        $detector->recordRateLimit(); // 1 -> 1

        // Assert
        $this->assertEquals(1, $detector->getCurrentConcurrency());
    }

    public function test_rate_limit_expira_apos_timeout()
    {
        // Arrange
        $detector = new RateLimitDetector();
        $detector->recordRateLimit();
        $this->assertTrue($detector->isRateLimited());

        // Act - Limpar cache (simula expiração)
        Cache::forget('ai_last_429_timestamp');

        // Assert - Não deve estar mais em rate limit
        $this->assertFalse($detector->isRateLimited());
    }
}
