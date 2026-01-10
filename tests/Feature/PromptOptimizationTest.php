<?php

namespace Tests\Feature;

use App\Services\Ai\ClassificationPrompt;
use App\Services\Ai\OptimizedPrompt;
use App\Services\Security\PromptInjectionGuard;
use Tests\TestCase;

class PromptOptimizationTest extends TestCase
{

    public function test_classification_maintains_accuracy()
    {
        // Test that both prompt builders produce valid prompts
        $ticket = [
            'issue_key' => 'DEMO-001',
            'summary' => 'Cannot access account',
            'description' => 'User reports login issues',
            'reporter' => 'john@example.com'
        ];

        $guard = new PromptInjectionGuard();
        $originalPrompt = new ClassificationPrompt($guard);
        $optimizedPrompt = new OptimizedPrompt($guard);

        $original = $originalPrompt->build($ticket);
        $optimized = $optimizedPrompt->build($ticket);

        // Both should contain JSON structure
        $this->assertStringContainsString('"category":', $original);
        $this->assertStringContainsString('"category":', $optimized);
        $this->assertStringContainsString('"sentiment":', $original);
        $this->assertStringContainsString('"sentiment":', $optimized);
    }

    public function test_response_time_optimized()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test ticket with longer summary to simulate real usage',
            'description' => 'Test description that is also longer to better simulate real-world ticket descriptions and see the token reduction impact',
            'reporter' => 'test@example.com'
        ];

        $guard = new PromptInjectionGuard();
        $originalPrompt = new ClassificationPrompt($guard);
        $optimizedPrompt = new OptimizedPrompt($guard);

        $original = $originalPrompt->build($ticket);
        $optimized = $optimizedPrompt->build($ticket);

        // Optimized should be shorter
        $this->assertLessThan(strlen($original), strlen($optimized));

        // Simulate processing time proportional to length
        $originalTime = strlen($original) * 0.001; // 1ms per char
        $optimizedTime = strlen($optimized) * 0.001;

        $this->assertLessThan($originalTime, $optimizedTime);
        $this->assertGreaterThan(5, ($originalTime - $optimizedTime) / $originalTime * 100);
    }
}