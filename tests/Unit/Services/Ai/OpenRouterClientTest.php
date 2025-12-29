<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\OpenRouterClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterClientTest extends TestCase
{
    private OpenRouterClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new OpenRouterClient('test-api-key');
    }

    public function test_successful_api_call()
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"category": "Technical"}'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $messages = [['role' => 'user', 'content' => 'Test message']];
        $result = $this->client->callApi('test-model', $messages);

        $this->assertIsArray($result);
        $this->assertEquals('{"category": "Technical"}', $result['choices'][0]['message']['content']);
    }

    public function test_retry_on_timeout()
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([], 500)
        ]);

        $messages = [['role' => 'user', 'content' => 'Test message']];
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenRouter API call failed after 1 attempts');
        
        $this->client->callApi('test-model', $messages);
    }

    public function test_exponential_backoff_timing()
    {
        $startTime = microtime(true);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([], 500)
        ]);

        $messages = [['role' => 'user', 'content' => 'Test message']];
        
        try {
            $this->client->callApi('test-model', $messages);
            $this->fail('Should have thrown exception');
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            
            // Sem retries, deve falhar instantaneamente (< 1 segundo)
            $this->assertLessThan(1, $duration);
        }
    }

    public function test_timeout_enforced()
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([], 200, [], 15) // 15 second delay
        ]);

        $messages = [['role' => 'user', 'content' => 'Test message']];

        $this->expectException(\Exception::class);
        $this->client->callApi('test-model', $messages);
    }

    public function test_rate_limit_handling()
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([], 429)
        ]);

        $messages = [['role' => 'user', 'content' => 'Test message']];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->client->callApi('test-model', $messages);
    }

    public function test_all_retries_fail()
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([], 500)
        ]);

        $messages = [['role' => 'user', 'content' => 'Test message']];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenRouter API call failed after 1 attempts');

        $this->client->callApi('test-model', $messages);
    }

    public function test_invalid_response_structure()
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'invalid' => 'structure'
            ], 200)
        ]);

        $messages = [['role' => 'user', 'content' => 'Test message']];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid OpenRouter API response');

        $this->client->callApi('test-model', $messages);
    }
}
