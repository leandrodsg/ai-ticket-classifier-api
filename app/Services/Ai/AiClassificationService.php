<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;

class AiClassificationService
{
    private const GLOBAL_TIMEOUT_SECONDS = 30;

    private array $models;
    private ModelDiscoveryService $discoveryService;

    public function __construct(
        private OpenRouterClient $client,
        private ClassificationPrompt $promptBuilder,
        ModelDiscoveryService $discoveryService
    ) {
        $this->discoveryService = $discoveryService;
        $this->models = config('ai.default_models');
    }

    public function classify(array $ticket): array
    {
        $startTime = microtime(true);

        // Tenta modelos padrão primeiro
        foreach ($this->models as $model) {
            try {
                $result = $this->tryModel($model, $ticket, $startTime);

                Log::info('AI classification successful', [
                    'model' => $model,
                    'ticket_key' => $ticket['issue_key'],
                    'processing_time_ms' => (microtime(true) - $startTime) * 1000
                ]);

                // Add model_used to result for tracking
                $result['model_used'] = $model;

                return $result;

            } catch (ValidationException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::warning('Model failed, trying next', [
                    'model' => $model,
                    'ticket_key' => $ticket['issue_key'],
                    'error' => $e->getMessage()
                ]);

                continue;
            }
        }

        // Se todos os modelos padrão falharam, tenta auto-discovery
        if (config('ai.fallback.use_discovery_on_failure')) {
            return $this->tryDiscoveredModels($ticket, $startTime);
        }

        throw new AllModelsFailedException('All AI models failed to classify ticket');
    }

    private function tryModel(string $model, array $ticket, float $startTime): array
    {
        $prompt = $this->promptBuilder->build($ticket);

        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];

        $response = $this->client->callApi($model, $messages);

        $content = $response['choices'][0]['message']['content'];
        $classification = json_decode($content, true);

        if (!$classification) {
            throw new ValidationException('Invalid JSON response from AI model');
        }

        $this->validateClassification($classification);

        return $classification;
    }

    private function validateClassification(array $classification): void
    {
        $required = ['category', 'sentiment', 'impact', 'urgency', 'reasoning'];

        foreach ($required as $field) {
            if (!isset($classification[$field])) {
                throw new ValidationException("Missing required field: {$field}");
            }
        }

        $validCategories = ['Technical', 'Commercial', 'Billing', 'General', 'Support'];
        if (!in_array($classification['category'], $validCategories)) {
            throw new ValidationException("Invalid category: {$classification['category']}");
        }

        $validSentiments = ['Positive', 'Negative', 'Neutral'];
        if (!in_array($classification['sentiment'], $validSentiments)) {
            throw new ValidationException("Invalid sentiment: {$classification['sentiment']}");
        }

        $validLevels = ['High', 'Medium', 'Low'];
        if (!in_array($classification['impact'], $validLevels)) {
            throw new ValidationException("Invalid impact: {$classification['impact']}");
        }

        if (!in_array($classification['urgency'], $validLevels)) {
            throw new ValidationException("Invalid urgency: {$classification['urgency']}");
        }
    }

    /**
     * Tenta usar modelos descobertos automaticamente como último recurso.
     *
     * @param array $ticket Dados do ticket
     * @param float $startTime Timestamp de início
     * @return array Classificação
     * @throws AllModelsFailedException
     */
    private function tryDiscoveredModels(array $ticket, float $startTime): array
    {
        Log::info('Tentando modelos descobertos via auto-discovery', [
            'ticket_key' => $ticket['issue_key']
        ]);

        $discoveredModels = $this->discoveryService->discoverFreeModels();

        if (empty($discoveredModels)) {
            Log::error('Nenhum modelo descoberto via auto-discovery');
            throw new AllModelsFailedException('All default models failed and no models discovered');
        }

        foreach ($discoveredModels as $modelData) {
            $model = $modelData['id'];
            
            try {
                $result = $this->tryModel($model, $ticket, $startTime);

                Log::info('AI classification successful with discovered model', [
                    'model' => $model,
                    'ticket_key' => $ticket['issue_key'],
                    'processing_time_ms' => (microtime(true) - $startTime) * 1000
                ]);

                // Add model_used to result for tracking
                $result['model_used'] = $model;

                return $result;

            } catch (ValidationException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::warning('Discovered model failed, trying next', [
                    'model' => $model,
                    'ticket_key' => $ticket['issue_key'],
                    'error' => $e->getMessage()
                ]);

                continue;
            }
        }

        throw new AllModelsFailedException('All models (default + discovered) failed to classify ticket');
    }
}
