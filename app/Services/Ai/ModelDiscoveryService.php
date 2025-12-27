<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ModelDiscoveryService
{
    private string $apiKey;
    private string $baseUrl = 'https://openrouter.ai/api/v1';
    private array $config;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key');
        $this->config = config('ai.auto_discovery');
    }

    /**
     * Descobre modelos free disponíveis no OpenRouter.
     * Usa cache para evitar múltiplas requisições.
     *
     * @return array Lista de modelos free ordenados por ranking
     */
    public function discoverFreeModels(): array
    {
        if (!$this->config['enabled']) {
            Log::info('Auto-discovery desabilitado via config');
            return [];
        }

        $cacheKey = config('ai.cache.key_prefix') . 'discovered_free';
        $cacheTtl = $this->config['cache_ttl'];

        return Cache::remember($cacheKey, $cacheTtl, function () {
            try {
                Log::info('Iniciando descoberta de modelos free no OpenRouter');
                
                $request = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => config('app.name'),
                ])
                ->timeout(15);

                // Bypass SSL verification in testing environment (Windows dev)
                if (app()->environment('local', 'testing')) {
                    $request->withoutVerifying();
                }

                $response = $request->get($this->baseUrl . '/models');

                if (!$response->successful()) {
                    Log::error('Falha ao buscar modelos do OpenRouter', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return [];
                }

                $models = $response->json('data', []);
                $freeModels = $this->filterFreeModels($models);
                
                Log::info('Modelos free descobertos', [
                    'total_models' => count($models),
                    'free_models' => count($freeModels),
                    'top_5' => array_slice(array_column($freeModels, 'id'), 0, 5),
                ]);

                return $freeModels;

            } catch (\Exception $e) {
                Log::error('Erro na descoberta de modelos', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return [];
            }
        });
    }

    /**
     * Filtra modelos para retornar apenas os free e adequados.
     *
     * @param array $models Lista completa de modelos
     * @return array Modelos filtrados e ordenados
     */
    private function filterFreeModels(array $models): array
    {
        $filters = $this->config['filters'];
        $maxModels = $this->config['max_models'];

        $filtered = array_filter($models, function ($model) use ($filters) {
            // 1. Apenas modelos free
            if ($filters['free_only'] && !$this->isFreeModel($model)) {
                return false;
            }

            // 2. Apenas chat models
            if (isset($model['architecture']['modality']) && 
                $model['architecture']['modality'] !== 'text->text') {
                return false;
            }

            // 3. Ranking mínimo
            $ranking = $model['top_provider']['max_completion_tokens'] ?? 0;
            if ($ranking < $filters['min_ranking']) {
                return false;
            }

            // 4. Modelo deve estar ativo
            if (isset($model['pricing']) && 
                isset($model['pricing']['prompt']) && 
                $model['pricing']['prompt'] === '-1') {
                return false;
            }

            return true;
        });

        // Ordena por ranking (maior primeiro)
        usort($filtered, function ($a, $b) {
            $rankA = $this->getModelRanking($a);
            $rankB = $this->getModelRanking($b);
            return $rankB <=> $rankA;
        });

        // Limita ao máximo configurado
        $filtered = array_slice($filtered, 0, $maxModels);

        // Retorna apenas os IDs dos modelos
        return array_map(function ($model) {
            return [
                'id' => $model['id'],
                'name' => $model['name'] ?? $model['id'],
                'ranking' => $this->getModelRanking($model),
                'context_length' => $model['context_length'] ?? 0,
            ];
        }, $filtered);
    }

    /**
     * Verifica se modelo é gratuito.
     *
     * @param array $model Dados do modelo
     * @return bool
     */
    private function isFreeModel(array $model): bool
    {
        // Verifica se tem :free no ID
        if (str_ends_with($model['id'], ':free')) {
            return true;
        }

        // Verifica se pricing é zero
        if (isset($model['pricing'])) {
            $prompt = (float) ($model['pricing']['prompt'] ?? 1);
            $completion = (float) ($model['pricing']['completion'] ?? 1);
            
            if ($prompt === 0.0 && $completion === 0.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtém ranking do modelo.
     *
     * @param array $model Dados do modelo
     * @return int
     */
    private function getModelRanking(array $model): int
    {
        // OpenRouter usa diferentes métricas para ranking
        // Vamos combinar várias para criar um score
        $score = 0;

        // Context length (maior = melhor)
        $score += ($model['context_length'] ?? 0) / 1000;

        // Top provider max tokens
        $score += ($model['top_provider']['max_completion_tokens'] ?? 0) / 100;

        // Popularidade (se disponível)
        if (isset($model['pricing']['requests'])) {
            $score += min($model['pricing']['requests'] / 10000, 100);
        }

        return (int) $score;
    }

    /**
     * Obtém o melhor modelo free disponível.
     *
     * @return string|null ID do melhor modelo ou null se nenhum encontrado
     */
    public function getBestFreeModel(): ?string
    {
        $models = $this->discoverFreeModels();
        
        if (empty($models)) {
            return null;
        }

        return $models[0]['id'];
    }

    /**
     * Limpa cache de modelos descobertos.
     *
     * @return void
     */
    public function clearCache(): void
    {
        $cacheKey = config('ai.cache.key_prefix') . 'discovered_free';
        Cache::forget($cacheKey);
        Log::info('Cache de modelos descobertos limpo');
    }
}
