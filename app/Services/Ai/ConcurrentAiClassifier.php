<?php

namespace App\Services\Ai;

use App\Services\Ai\Strategies\ConcurrentStrategy;
use Illuminate\Support\Facades\Log;

class ConcurrentAiClassifier
{
    public function __construct(
        private OpenRouterClient $client,
        private ClassificationPrompt $promptBuilder,
        private ModelDiscoveryService $discoveryService,
        private RateLimitDetector $rateLimitDetector,
        private ConcurrentStrategy $concurrentStrategy
    ) {}

    /**
     * Classifica um batch de tickets usando processamento concorrente
     *
     * @param array $tickets Array de tickets para classificar
     * @return array Array de classificações na mesma ordem dos tickets
     */
    public function classifyBatch(array $tickets): array
    {
        if (empty($tickets)) {
            return [];
        }

        $totalTickets = count($tickets);
        Log::info('Starting concurrent classification', ['total_tickets' => $totalTickets]);

        try {
            // Tentar processamento concorrente primeiro
            $results = $this->concurrentStrategy->processBatch($tickets);

            // Verificar se todos os resultados são válidos
            $validResults = array_filter($results, fn($result) => $result !== null);

            if (count($validResults) === $totalTickets) {
                Log::info('Concurrent classification completed successfully', [
                    'total_tickets' => $totalTickets
                ]);
                return $results;
            }

            // Se alguns falharam, tentar fallback sequencial para os que falharam
            Log::warning('Some tickets failed concurrent processing, attempting fallback', [
                'successful' => count($validResults),
                'failed' => $totalTickets - count($validResults)
            ]);

            return $this->fallbackSequential($tickets, $results);

        } catch (\Exception $e) {
            Log::error('Concurrent processing failed completely, falling back to sequential', [
                'error' => $e->getMessage(),
                'total_tickets' => $totalTickets
            ]);

            return $this->fallbackSequential($tickets);
        }
    }

    /**
     * Fallback para processamento sequencial quando concorrente falha
     */
    private function fallbackSequential(array $tickets, array $existingResults = []): array
    {
        $results = $existingResults;
        $sequentialClassifier = new AiClassificationService(
            $this->client,
            $this->promptBuilder,
            $this->discoveryService
        );

        foreach ($tickets as $index => $ticket) {
            // Pular se já temos resultado válido
            if (isset($results[$index]) && $results[$index] !== null) {
                continue;
            }

            try {
                $results[$index] = $sequentialClassifier->classify($ticket);

                Log::info('Sequential fallback successful', [
                    'ticket_key' => $ticket['issue_key'],
                    'index' => $index
                ]);

            } catch (\Exception $e) {
                Log::error('Sequential fallback also failed', [
                    'ticket_key' => $ticket['issue_key'],
                    'index' => $index,
                    'error' => $e->getMessage()
                ]);

                // Para tickets que falham completamente, retornar classificação básica
                $results[$index] = $this->createFallbackClassification($ticket);
            }
        }

        return $results;
    }

    /**
     * Cria uma classificação fallback básica para casos extremos
     */
    private function createFallbackClassification(array $ticket): array
    {
        return [
            'category' => 'General',
            'sentiment' => 'Neutral',
            'impact' => 'Medium',
            'urgency' => 'Medium',
            'reasoning' => 'Fallback classification due to processing failure',
            'model_used' => 'fallback'
        ];
    }

    /**
     * Divide tickets em batches para processamento
     */
    private function createBatches(array $tickets, int $batchSize): array
    {
        return array_chunk($tickets, $batchSize);
    }

    /**
     * Calcula estatísticas do processamento
     */
    public function getProcessingStats(): array
    {
        return [
            'current_concurrency' => $this->rateLimitDetector->getCurrentConcurrency(),
            'is_rate_limited' => $this->rateLimitDetector->isRateLimited(),
            'recommended_delay_ms' => $this->rateLimitDetector->getDelayMs(),
        ];
    }
}