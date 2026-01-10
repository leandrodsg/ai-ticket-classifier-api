<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\ConcurrentAiClassifier;
use App\Services\Ai\OpenRouterClient;
use App\Services\Ai\PromptBuilderInterface;
use App\Services\Ai\ModelDiscoveryService;
use App\Services\Ai\RateLimitDetector;
use App\Services\Ai\Strategies\ConcurrentStrategy;
use Tests\TestCase;
use Mockery;

class ConcurrentAiClassifierTest extends TestCase
{
    private function createMockTickets(int $count): array
    {
        $tickets = [];
        for ($i = 1; $i <= $count; $i++) {
            $tickets[] = [
                'issue_key' => "TICKET-{$i}",
                'summary' => "Test Summary {$i}",
                'description' => "Test Description {$i}",
                'reporter' => "user{$i}@example.com",
                'created' => now()->toISOString()
            ];
        }
        return $tickets;
    }

    private function createMockClassification(): array
    {
        return [
            'category' => 'Technical',
            'sentiment' => 'Neutral',
            'impact' => 'Medium',
            'urgency' => 'High',
            'reasoning' => 'Test classification',
            'model_used' => 'test-model'
        ];
    }

    public function test_concurrent_classifier_can_be_instantiated()
    {
        $client = $this->mock(OpenRouterClient::class);
        $prompt = $this->mock(PromptBuilderInterface::class);
        $discovery = $this->mock(ModelDiscoveryService::class);
        $rateLimitDetector = $this->mock(RateLimitDetector::class);
        $strategy = $this->mock(ConcurrentStrategy::class);

        $classifier = new ConcurrentAiClassifier(
            $client,
            $prompt,
            $discovery,
            $rateLimitDetector,
            $strategy
        );

        $this->assertInstanceOf(ConcurrentAiClassifier::class, $classifier);
    }

    public function test_classify_batch_returns_empty_array_for_empty_input()
    {
        $client = $this->mock(OpenRouterClient::class);
        $prompt = $this->mock(PromptBuilderInterface::class);
        $discovery = $this->mock(ModelDiscoveryService::class);
        $rateLimitDetector = $this->mock(RateLimitDetector::class);
        $strategy = $this->mock(ConcurrentStrategy::class);

        $classifier = new ConcurrentAiClassifier(
            $client,
            $prompt,
            $discovery,
            $rateLimitDetector,
            $strategy
        );

        $result = $classifier->classifyBatch([]);

        $this->assertEquals([], $result);
    }

    public function test_processa_4_tickets_concorrentemente()
    {
        // Arrange
        $tickets = $this->createMockTickets(4);
        $expectedResults = array_fill(0, 4, $this->createMockClassification());

        $client = $this->mock(OpenRouterClient::class);
        $prompt = $this->mock(PromptBuilderInterface::class);
        $discovery = $this->mock(ModelDiscoveryService::class);
        $rateLimitDetector = $this->mock(RateLimitDetector::class);
        
        $strategy = $this->mock(ConcurrentStrategy::class);
        $strategy->shouldReceive('processBatch')
            ->once()
            ->with($tickets)
            ->andReturn($expectedResults);

        $classifier = new ConcurrentAiClassifier(
            $client,
            $prompt,
            $discovery,
            $rateLimitDetector,
            $strategy
        );

        // Act
        $startTime = microtime(true);
        $results = $classifier->classifyBatch($tickets);
        $endTime = microtime(true);

        // Assert
        $this->assertCount(4, $results);
        $this->assertEquals($expectedResults, $results);
        
        // Deve ser rápido com mock (menos de 1 segundo)
        $processingTime = $endTime - $startTime;
        $this->assertLessThan(1.0, $processingTime);
    }

    public function test_processa_15_tickets_em_lotes()
    {
        // Arrange
        $tickets = $this->createMockTickets(15);
        $expectedResults = array_fill(0, 15, $this->createMockClassification());

        $client = $this->mock(OpenRouterClient::class);
        $prompt = $this->mock(PromptBuilderInterface::class);
        $discovery = $this->mock(ModelDiscoveryService::class);
        $rateLimitDetector = $this->mock(RateLimitDetector::class);
        
        $strategy = $this->mock(ConcurrentStrategy::class);
        $strategy->shouldReceive('processBatch')
            ->once()
            ->with($tickets)
            ->andReturn($expectedResults);

        $classifier = new ConcurrentAiClassifier(
            $client,
            $prompt,
            $discovery,
            $rateLimitDetector,
            $strategy
        );

        // Act
        $results = $classifier->classifyBatch($tickets);

        // Assert
        $this->assertCount(15, $results);
        $this->assertEquals($expectedResults, $results);
    }

    public function test_fallback_para_sequencial_em_erro()
    {
        // Arrange
        $tickets = $this->createMockTickets(4);

        $client = $this->mock(OpenRouterClient::class);
        $prompt = $this->mock(PromptBuilderInterface::class);
        $discovery = $this->mock(ModelDiscoveryService::class);
        $rateLimitDetector = $this->mock(RateLimitDetector::class);
        
        // Strategy falha
        $strategy = $this->mock(ConcurrentStrategy::class);
        $strategy->shouldReceive('processBatch')
            ->once()
            ->andThrow(new \Exception('Concurrent processing failed'));

        // Mock do fallback sequencial - AiClassificationService
        config(['ai.default_models' => ['test-model']]);
        
        $prompt->shouldReceive('build')
            ->times(4)
            ->andReturn('test prompt');
            
        $client->shouldReceive('callApi')
            ->times(4)
            ->with('test-model', Mockery::any())
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode($this->createMockClassification())]]
                ]
            ]);

        $classifier = new ConcurrentAiClassifier(
            $client,
            $prompt,
            $discovery,
            $rateLimitDetector,
            $strategy
        );

        // Act
        $results = $classifier->classifyBatch($tickets);

        // Assert - Todos tickets foram processados via fallback
        $this->assertCount(4, $results);
        foreach ($results as $result) {
            $this->assertArrayHasKey('category', $result);
            $this->assertArrayHasKey('sentiment', $result);
            $this->assertArrayHasKey('model_used', $result);
        }
    }

    public function test_respeita_limite_de_concorrencia()
    {
        // Arrange
        $tickets = $this->createMockTickets(20);
        $expectedResults = array_fill(0, 20, $this->createMockClassification());

        $client = $this->mock(OpenRouterClient::class);
        $prompt = $this->mock(PromptBuilderInterface::class);
        $discovery = $this->mock(ModelDiscoveryService::class);
        $rateLimitDetector = $this->mock(RateLimitDetector::class);
        
        // Verificar que processa em lotes
        $strategy = $this->mock(ConcurrentStrategy::class);
        $strategy->shouldReceive('processBatch')
            ->once()
            ->with($tickets)
            ->andReturn($expectedResults);

        $classifier = new ConcurrentAiClassifier(
            $client,
            $prompt,
            $discovery,
            $rateLimitDetector,
            $strategy
        );

        // Act
        $results = $classifier->classifyBatch($tickets);

        // Assert
        $this->assertCount(20, $results);
    }

    public function test_get_processing_stats_returns_expected_structure()
    {
        $client = $this->mock(OpenRouterClient::class);
        $prompt = $this->mock(PromptBuilderInterface::class);
        $discovery = $this->mock(ModelDiscoveryService::class);
        $rateLimitDetector = $this->mock(RateLimitDetector::class);
        $strategy = $this->mock(ConcurrentStrategy::class);

        $rateLimitDetector->shouldReceive('getCurrentConcurrency')->andReturn(4);
        $rateLimitDetector->shouldReceive('isRateLimited')->andReturn(false);
        $rateLimitDetector->shouldReceive('getDelayMs')->andReturn(0);

        $classifier = new ConcurrentAiClassifier(
            $client,
            $prompt,
            $discovery,
            $rateLimitDetector,
            $strategy
        );

        $stats = $classifier->getProcessingStats();

        $this->assertArrayHasKey('current_concurrency', $stats);
        $this->assertArrayHasKey('is_rate_limited', $stats);
        $this->assertArrayHasKey('recommended_delay_ms', $stats);
        $this->assertEquals(4, $stats['current_concurrency']);
        $this->assertFalse($stats['is_rate_limited']);
        $this->assertEquals(0, $stats['recommended_delay_ms']);
    }
}
