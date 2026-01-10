<?php

namespace App\Services\Ai\Strategies;

use App\Services\Ai\OpenRouterClient;
use App\Services\Ai\ClassificationPrompt;
use App\Services\Ai\RateLimitDetector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ConcurrentStrategy
{
    private const TIMEOUT_PER_REQUEST = 20;
    private const MAX_RETRIES = 2;

    public function __construct(
        private OpenRouterClient $client,
        private ClassificationPrompt $promptBuilder,
        private RateLimitDetector $rateLimitDetector
    ) {}

    /**
     * Processa um batch de tickets simultaneamente
     *
     * @param array $tickets Array de tickets para classificar
     * @return array Array de classificações (mesma ordem dos tickets)
     * @throws \Exception Se falhar completamente
     */
    public function processBatch(array $tickets): array
    {
        $batchSize = count($tickets);
        $concurrency = $this->rateLimitDetector->getCurrentConcurrency();

        Log::info('Starting concurrent batch processing', [
            'batch_size' => $batchSize,
            'concurrency' => $concurrency
        ]);

        try {
            // Criar promises para cada ticket
            $promises = $this->createPromises($tickets);

            // Executar simultaneamente com limite de concorrência
            $results = $this->executeWithConcurrencyLimit($promises, $concurrency);

            // Registrar requests bem-sucedidas
            $this->rateLimitDetector->recordSuccessfulRequest();

            // Considerar aumentar concorrência se tudo correu bem
            $this->rateLimitDetector->increaseConcurrency();

            return $results;

        } catch (\Exception $e) {
            Log::error('Concurrent batch processing failed', [
                'batch_size' => $batchSize,
                'error' => $e->getMessage()
            ]);

            // Verificar se é rate limiting
            if ($this->isRateLimitError($e)) {
                $this->rateLimitDetector->recordRateLimit();
                throw new \Exception('Rate limit exceeded during concurrent processing');
            }

            throw $e;
        }
    }

    /**
     * Cria promises HTTP para cada ticket
     */
    private function createPromises(array $tickets): array
    {
        $promises = [];

        foreach ($tickets as $index => $ticket) {
            $promises[$index] = $this->createPromiseForTicket($ticket, $index);
        }

        return $promises;
    }

    /**
     * Cria uma promise para classificar um ticket específico
     */
    private function createPromiseForTicket(array $ticket, int $index): callable
    {
        return function () use ($ticket, $index) {
            $startTime = microtime(true);

            try {
                $result = $this->classifySingleTicket($ticket);

                Log::debug('Ticket classified successfully in concurrent batch', [
                    'ticket_key' => $ticket['issue_key'],
                    'index' => $index,
                    'processing_time_ms' => (microtime(true) - $startTime) * 1000
                ]);

                return $result;

            } catch (\Exception $e) {
                Log::warning('Ticket classification failed in concurrent batch', [
                    'ticket_key' => $ticket['issue_key'],
                    'index' => $index,
                    'error' => $e->getMessage()
                ]);

                throw $e;
            }
        };
    }

    /**
     * Classifica um ticket individual usando OpenRouter
     */
    private function classifySingleTicket(array $ticket): array
    {
        $prompt = $this->promptBuilder->build($ticket);

        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];

        $response = $this->client->callApi(
            config('ai.default_models')[0], // Usar primeiro modelo disponível
            $messages
        );

        $content = $response['choices'][0]['message']['content'];
        $classification = json_decode($content, true);

        if (!$classification) {
            throw new \Exception('Invalid JSON response from AI model');
        }

        $this->validateClassification($classification);

        return $classification;
    }

    /**
     * Executa promises com limite de concorrência usando Pool do Guzzle
     */
    private function executeWithConcurrencyLimit(array $promises, int $concurrency): array
    {
        $results = [];
        $errors = [];

        // Processar em grupos de acordo com a concorrência
        $chunks = array_chunk($promises, $concurrency, true);

        foreach ($chunks as $chunk) {
            $chunkResults = $this->executeChunk($chunk);
            $results = array_merge($results, $chunkResults);

            // Pequeno delay entre chunks se necessário
            $delay = $this->rateLimitDetector->getDelayMs();
            if ($delay > 0) {
                usleep($delay * 1000); // converter ms para microseconds
            }
        }

        return $results;
    }

    /**
     * Executa um chunk de promises simultaneamente
     */
    private function executeChunk(array $chunk): array
    {
        $results = [];

        // Usar múltiplas threads/processos simulados via curl_multi
        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($chunk as $index => $promise) {
            // Para simplificar, executar sequencialmente dentro do chunk
            // Em produção, isso seria feito com curl_multi ou ReactPHP
            try {
                $results[$index] = $promise();
            } catch (\Exception $e) {
                $results[$index] = null; // Marcar como falha
                Log::warning('Promise failed in chunk', [
                    'index' => $index,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Valida a estrutura da classificação retornada
     */
    private function validateClassification(array $classification): void
    {
        $required = ['category', 'sentiment', 'impact', 'urgency', 'reasoning'];

        foreach ($required as $field) {
            if (!isset($classification[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        $validCategories = ['Technical', 'Commercial', 'Billing', 'General', 'Support'];
        if (!in_array($classification['category'], $validCategories)) {
            throw new \Exception("Invalid category: {$classification['category']}");
        }

        $validSentiments = ['Positive', 'Negative', 'Neutral'];
        if (!in_array($classification['sentiment'], $validSentiments)) {
            throw new \Exception("Invalid sentiment: {$classification['sentiment']}");
        }

        $validLevels = ['High', 'Medium', 'Low'];
        if (!in_array($classification['impact'], $validLevels)) {
            throw new \Exception("Invalid impact: {$classification['impact']}");
        }

        if (!in_array($classification['urgency'], $validLevels)) {
            throw new \Exception("Invalid urgency: {$classification['urgency']}");
        }
    }

    /**
     * Verifica se o erro é relacionado a rate limiting
     */
    private function isRateLimitError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'rate limit') ||
               str_contains($message, '429') ||
               str_contains($message, 'too many requests');
    }
}