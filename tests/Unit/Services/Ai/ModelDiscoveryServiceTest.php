<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\ModelDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ModelDiscoveryServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private ModelDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('services.openrouter.api_key', 'test-api-key');
        Config::set('app.url', 'http://localhost');
        Config::set('app.name', 'Test App');
        Config::set('ai.auto_discovery.enabled', true);
        Config::set('ai.auto_discovery.cache_ttl', 3600);
        Config::set('ai.auto_discovery.max_models', 5);
        Config::set('ai.auto_discovery.filters', [
            'free_only' => true,
            'min_ranking' => 100,
        ]);
        Config::set('ai.cache.key_prefix', 'ai_models:');
        
        Cache::flush();
        $this->service = new ModelDiscoveryService();
    }

    public function test_discover_free_models_returns_cached_result_on_second_call()
    {
        $mockModels = [
            ['id' => 'model-1:free', 'name' => 'Free Model 1', 'context_length' => 4096, 
             'architecture' => ['modality' => 'text->text'],
             'top_provider' => ['max_completion_tokens' => 2048],
             'pricing' => ['prompt' => '0', 'completion' => '0']],
        ];

        Http::fake([
            'openrouter.ai/*' => Http::response(['data' => $mockModels], 200)
        ]);

        // First call - hits API
        $result1 = $this->service->discoverFreeModels();
        
        // Second call - should use cache (Http::fake would fail if called again)
        $result2 = $this->service->discoverFreeModels();

        $this->assertEquals($result1, $result2);
        $this->assertNotEmpty($result1);
        
        Http::assertSentCount(1); // Only one API call
    }

    public function test_discover_free_models_filters_only_free_models()
    {
        $mockModels = [
            ['id' => 'model-1:free', 'name' => 'Free Model', 'context_length' => 4096,
             'architecture' => ['modality' => 'text->text'],
             'top_provider' => ['max_completion_tokens' => 2048],
             'pricing' => ['prompt' => '0', 'completion' => '0']],
            ['id' => 'model-2-paid', 'name' => 'Paid Model', 'context_length' => 8192,
             'architecture' => ['modality' => 'text->text'],
             'top_provider' => ['max_completion_tokens' => 4096],
             'pricing' => ['prompt' => '0.0015', 'completion' => '0.002']],
        ];

        Http::fake([
            'openrouter.ai/*' => Http::response(['data' => $mockModels], 200)
        ]);

        $result = $this->service->discoverFreeModels();

        $this->assertCount(1, $result);
        $this->assertEquals('model-1:free', $result[0]['id']);
    }

    public function test_discover_free_models_orders_by_ranking()
    {
        $mockModels = [
            ['id' => 'model-low:free', 'name' => 'Low Rank', 'context_length' => 2048,
             'architecture' => ['modality' => 'text->text'],
             'top_provider' => ['max_completion_tokens' => 512],
             'pricing' => ['prompt' => '0', 'completion' => '0']],
            ['id' => 'model-high:free', 'name' => 'High Rank', 'context_length' => 8192,
             'architecture' => ['modality' => 'text->text'],
             'top_provider' => ['max_completion_tokens' => 4096],
             'pricing' => ['prompt' => '0', 'completion' => '0']],
        ];

        Http::fake([
            'openrouter.ai/*' => Http::response(['data' => $mockModels], 200)
        ]);

        $result = $this->service->discoverFreeModels();

        $this->assertEquals('model-high:free', $result[0]['id']);
        $this->assertEquals('model-low:free', $result[1]['id']);
        $this->assertGreaterThan($result[1]['ranking'], $result[0]['ranking']);
    }

    public function test_discover_free_models_respects_max_models_limit()
    {
        Config::set('ai.auto_discovery.max_models', 2);
        
        // Recreate service to pick up new config
        $this->service = new ModelDiscoveryService();
        
        $mockModels = [];
        for ($i = 1; $i <= 5; $i++) {
            $mockModels[] = [
                'id' => "model-{$i}:free",
                'name' => "Model {$i}",
                'context_length' => 4096,
                'architecture' => ['modality' => 'text->text'],
                'top_provider' => ['max_completion_tokens' => 2048],
                'pricing' => ['prompt' => '0', 'completion' => '0']
            ];
        }

        Http::fake([
            'openrouter.ai/*' => Http::response(['data' => $mockModels], 200)
        ]);

        $result = $this->service->discoverFreeModels();

        $this->assertCount(2, $result);
    }

    public function test_discover_free_models_returns_empty_when_disabled()
    {
        Config::set('ai.auto_discovery.enabled', false);
        
        // Recreate service to pick up new config
        $this->service = new ModelDiscoveryService();

        Log::shouldReceive('info')
            ->with('Auto-discovery desabilitado via config')
            ->once();

        $result = $this->service->discoverFreeModels();

        $this->assertEmpty($result);
    }

    public function test_discover_free_models_handles_api_failure_gracefully()
    {
        Http::fake([
            'openrouter.ai/*' => Http::response('Server Error', 500)
        ]);

        Log::shouldReceive('info')
            ->with('Iniciando descoberta de modelos free no OpenRouter')
            ->once();
            
        Log::shouldReceive('error')
            ->once()
            ->with('Falha ao buscar modelos do OpenRouter', \Mockery::any());

        $result = $this->service->discoverFreeModels();

        $this->assertEmpty($result);
    }

    public function test_discover_free_models_handles_exception_gracefully()
    {
        Http::fake(function () {
            throw new \Exception('Network timeout');
        });

        Log::shouldReceive('info')
            ->with('Iniciando descoberta de modelos free no OpenRouter')
            ->once();
            
        Log::shouldReceive('error')
            ->once()
            ->with('Erro na descoberta de modelos', \Mockery::any());

        $result = $this->service->discoverFreeModels();

        $this->assertEmpty($result);
    }

    public function test_discover_free_models_filters_non_chat_models()
    {
        $mockModels = [
            ['id' => 'text-model:free', 'name' => 'Text Model', 'context_length' => 4096,
             'architecture' => ['modality' => 'text->text'],
             'top_provider' => ['max_completion_tokens' => 2048],
             'pricing' => ['prompt' => '0', 'completion' => '0']],
            ['id' => 'image-model:free', 'name' => 'Image Model', 'context_length' => 4096,
             'architecture' => ['modality' => 'text->image'],
             'top_provider' => ['max_completion_tokens' => 2048],
             'pricing' => ['prompt' => '0', 'completion' => '0']],
        ];

        Http::fake([
            'openrouter.ai/*' => Http::response(['data' => $mockModels], 200)
        ]);

        $result = $this->service->discoverFreeModels();

        $this->assertCount(1, $result);
        $this->assertEquals('text-model:free', $result[0]['id']);
    }

    public function test_discover_free_models_includes_model_metadata()
    {
        $mockModels = [
            ['id' => 'model-1:free', 'name' => 'Test Model', 'context_length' => 8192,
             'architecture' => ['modality' => 'text->text'],
             'top_provider' => ['max_completion_tokens' => 4096],
             'pricing' => ['prompt' => '0', 'completion' => '0']],
        ];

        Http::fake([
            'openrouter.ai/*' => Http::response(['data' => $mockModels], 200)
        ]);

        $result = $this->service->discoverFreeModels();

        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('ranking', $result[0]);
        $this->assertArrayHasKey('context_length', $result[0]);
        $this->assertEquals('model-1:free', $result[0]['id']);
        $this->assertEquals('Test Model', $result[0]['name']);
        $this->assertEquals(8192, $result[0]['context_length']);
    }

    public function test_get_best_free_model_returns_top_ranked_model()
    {
        $mockModels = [
            ['id' => 'model-low:free', 'name' => 'Low', 'context_length' => 2048,
             'architecture' => ['modality' => 'text->text'],
             'top_provider' => ['max_completion_tokens' => 512],
             'pricing' => ['prompt' => '0', 'completion' => '0']],
            ['id' => 'model-high:free', 'name' => 'High', 'context_length' => 8192,
             'architecture' => ['modality' => 'text->text'],
             'top_provider' => ['max_completion_tokens' => 4096],
             'pricing' => ['prompt' => '0', 'completion' => '0']],
        ];

        Http::fake([
            'openrouter.ai/*' => Http::response(['data' => $mockModels], 200)
        ]);

        $best = $this->service->getBestFreeModel();

        $this->assertEquals('model-high:free', $best);
    }

    public function test_get_best_free_model_returns_null_when_no_models()
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(['data' => []], 200)
        ]);

        $best = $this->service->getBestFreeModel();

        $this->assertNull($best);
    }

    public function test_clear_cache_removes_cached_models()
    {
        $mockModels = [
            ['id' => 'model-1:free', 'name' => 'Model', 'context_length' => 4096,
             'architecture' => ['modality' => 'text->text'],
             'top_provider' => ['max_completion_tokens' => 2048],
             'pricing' => ['prompt' => '0', 'completion' => '0']],
        ];

        Http::fake([
            'openrouter.ai/*' => Http::response(['data' => $mockModels], 200)
        ]);

        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('error')->never();

        // First call - caches result
        $this->service->discoverFreeModels();
        Http::assertSentCount(1);

        // Clear cache
        $this->service->clearCache();

        // Next call should hit API again
        $this->service->discoverFreeModels();
        Http::assertSentCount(2);
    }

    public function test_discover_free_models_identifies_free_by_id_suffix()
    {
        $mockModels = [
            ['id' => 'gpt-3.5-turbo:free', 'name' => 'GPT Free', 'context_length' => 4096,
             'architecture' => ['modality' => 'text->text'],
             'top_provider' => ['max_completion_tokens' => 2048],
             'pricing' => ['prompt' => '0.0015', 'completion' => '0.002']], // Has pricing but ID has :free
        ];

        Http::fake([
            'openrouter.ai/*' => Http::response(['data' => $mockModels], 200)
        ]);

        $result = $this->service->discoverFreeModels();

        $this->assertCount(1, $result);
        $this->assertEquals('gpt-3.5-turbo:free', $result[0]['id']);
    }

    public function test_discover_free_models_identifies_free_by_zero_pricing()
    {
        $mockModels = [
            ['id' => 'free-model', 'name' => 'Free Model', 'context_length' => 4096,
             'architecture' => ['modality' => 'text->text'],
             'top_provider' => ['max_completion_tokens' => 2048],
             'pricing' => ['prompt' => '0', 'completion' => '0']], // Zero pricing, no :free suffix
        ];

        Http::fake([
            'openrouter.ai/*' => Http::response(['data' => $mockModels], 200)
        ]);

        $result = $this->service->discoverFreeModels();

        $this->assertCount(1, $result);
        $this->assertEquals('free-model', $result[0]['id']);
    }
}
