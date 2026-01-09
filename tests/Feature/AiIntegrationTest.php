<?php

namespace Tests\Feature;

use App\Services\Ai\AiClassificationService;
use App\Services\Ai\ClassificationPrompt;
use App\Services\Ai\ModelDiscoveryService;
use App\Services\Ai\OpenRouterClient;
use App\Services\Security\PromptInjectionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AiIntegrationTest extends TestCase
{
    use RefreshDatabase;
    
    private AiClassificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Use real implementations for integration test
        $apiKey = config('services.openrouter.api_key') ?: env('OPENROUTER_API_KEY');
        if (!$apiKey || str_contains($apiKey, 'fake-key-for-ci-testing')) {
            $this->markTestSkipped('OpenRouter API key not configured or using fake key');
        }
        
        $client = new OpenRouterClient($apiKey);
        $guard = new PromptInjectionGuard();
        $promptBuilder = new ClassificationPrompt($guard);
        $discoveryService = new ModelDiscoveryService();
        $this->service = new AiClassificationService($client, $promptBuilder, $discoveryService);
    }

    public function test_ai_integration_with_real_openrouter_call()
    {
        // Set overall test timeout (60 seconds)
        set_time_limit(60);

        $ticket = [
            'issue_key' => 'DEMO-001',
            'summary' => 'Cannot access my account',
            'description' => 'User reports they cannot log in to their account after password reset. Error message: "Invalid credentials". User has tried multiple times.',
            'reporter' => 'john.doe@example.com'
        ];

        $startTime = microtime(true);

        try {
            $result = $this->service->classify($ticket);
            $endTime = microtime(true);

            // Verify response structure
            $this->assertIsArray($result);
            $this->assertArrayHasKey('category', $result);
            $this->assertArrayHasKey('sentiment', $result);
            $this->assertArrayHasKey('impact', $result);
            $this->assertArrayHasKey('urgency', $result);
            $this->assertArrayHasKey('reasoning', $result);

            // Verify enums
            $validCategories = ['Technical', 'Commercial', 'Billing', 'General', 'Support'];
            $this->assertContains($result['category'], $validCategories);

            $validSentiments = ['Positive', 'Negative', 'Neutral'];
            $this->assertContains($result['sentiment'], $validSentiments);

            $validLevels = ['High', 'Medium', 'Low'];
            $this->assertContains($result['impact'], $validLevels);
            $this->assertContains($result['urgency'], $validLevels);

            // Verify reasoning is present
            $this->assertIsString($result['reasoning']);
            $this->assertNotEmpty($result['reasoning']);

            // Verify timing (should be reasonable)
            $processingTime = ($endTime - $startTime) * 1000;
            $this->assertLessThan(75000, $processingTime, 'Processing should take less than 75 seconds');

            // Log successful test
            Log::info('AI Integration test passed', [
                'ticket_key' => $ticket['issue_key'],
                'category' => $result['category'],
                'processing_time_ms' => $processingTime
            ]);

        } catch (\Exception $e) {
            // Log failure but don't fail test - API might be down
            Log::warning('AI Integration test failed', [
                'ticket_key' => $ticket['issue_key'],
                'error' => $e->getMessage()
            ]);

            // If it's a rate limit or service unavailable, skip the test
            if (str_contains($e->getMessage(), 'Rate limit exceeded') ||
                str_contains($e->getMessage(), 'Service Unavailable')) {
                $this->markTestSkipped('OpenRouter API temporarily unavailable: ' . $e->getMessage());
            }

            // Re-throw other exceptions
            throw $e;
        }
    }

    public function test_ai_integration_measures_response_time()
    {
        $apiKey = config('services.openrouter.api_key') ?: env('OPENROUTER_API_KEY');
        if (!$apiKey) {
            $this->markTestSkipped('OpenRouter API key not configured');
        }

        // Set overall test timeout (60 seconds)
        set_time_limit(60);

        $ticket = [
            'issue_key' => 'PERF-001',
            'summary' => 'Performance test ticket',
            'description' => 'This is a simple test ticket to measure AI response time.',
            'reporter' => 'perf.test@example.com'
        ];

        $startTime = microtime(true);

        try {
            $result = $this->service->classify($ticket);
            $endTime = microtime(true);
            $processingTime = ($endTime - $startTime) * 1000;

            // Log performance metrics
            Log::info('AI Performance test completed', [
                'processing_time_ms' => $processingTime,
                'category' => $result['category'],
                'model_used' => 'meta-llama/llama-3.3-70b-instruct' // First model
            ]);

            // Performance should be reasonable (under 45 seconds for simple ticket)
            $this->assertLessThan(45000, $processingTime);

        } catch (\Exception $e) {
            // Skip if API is down
            if (str_contains($e->getMessage(), 'Rate limit') ||
                str_contains($e->getMessage(), 'Service Unavailable')) {
                $this->markTestSkipped('API unavailable for performance test');
            }
            throw $e;
        }
    }
}
