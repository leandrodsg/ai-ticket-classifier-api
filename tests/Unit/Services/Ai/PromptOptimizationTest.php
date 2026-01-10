<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\ClassificationPrompt;
use App\Services\Ai\OptimizedPrompt;
use App\Services\Security\PromptInjectionGuard;
use Tests\TestCase;

class PromptOptimizationTest extends TestCase
{
    private ClassificationPrompt $originalPrompt;
    private OptimizedPrompt $optimizedPrompt;

    protected function setUp(): void
    {
        parent::setUp();
        $guard = new PromptInjectionGuard();
        $this->originalPrompt = new ClassificationPrompt($guard);
        $this->optimizedPrompt = new OptimizedPrompt($guard);
    }

    public function test_optimized_prompt_has_fewer_tokens()
    {
        $ticket = [
            'issue_key' => 'DEMO-001',
            'summary' => 'Cannot access my account',
            'description' => 'User reports they cannot log in to their account after password reset.',
            'reporter' => 'john.doe@example.com'
        ];

        $original = $this->originalPrompt->build($ticket);
        $optimized = $this->optimizedPrompt->build($ticket);

        // Count approximate tokens (rough estimate: 1 token ≈ 4 characters)
        $originalTokens = strlen($original) / 4;
        $optimizedTokens = strlen($optimized) / 4;

        $this->assertLessThan($originalTokens, $optimizedTokens);
        $this->assertLessThanOrEqual(700, $optimizedTokens);
        $this->assertGreaterThanOrEqual(30, ($originalTokens - $optimizedTokens) / $originalTokens * 100);
    }

    public function test_optimized_prompt_maintains_json_structure()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test summary',
            'description' => 'Test description',
            'reporter' => 'test@example.com'
        ];

        $optimized = $this->optimizedPrompt->build($ticket);

        $this->assertStringContainsString('"category":', $optimized);
        $this->assertStringContainsString('"sentiment":', $optimized);
        $this->assertStringContainsString('"impact":', $optimized);
        $this->assertStringContainsString('"urgency":', $optimized);
        $this->assertStringContainsString('"reasoning":', $optimized);
        $this->assertStringContainsString('Technical|Commercial|Billing|General|Support', $optimized);
    }

    public function test_optimized_prompt_includes_security()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test summary',
            'description' => 'Test description',
            'reporter' => 'test@example.com'
        ];

        $optimized = $this->optimizedPrompt->build($ticket);

        $this->assertStringContainsString('IGNORE', $optimized);
        $this->assertStringContainsString('JSON', $optimized);
        $this->assertStringContainsString('untrusted', $optimized);
    }

    public function test_can_switch_between_versions()
    {
        // Test direct instantiation
        $this->assertInstanceOf(ClassificationPrompt::class, $this->originalPrompt);
        $this->assertInstanceOf(OptimizedPrompt::class, $this->optimizedPrompt);

        // Test config-based selection
        config(['ai.prompt.version' => 'original']);
        $app = app();
        $guard = $app->make(\App\Services\Security\PromptInjectionGuard::class);
        $promptBuilder = match (config('ai.prompt.version')) {
            'optimized' => new \App\Services\Ai\OptimizedPrompt($guard),
            default => new \App\Services\Ai\ClassificationPrompt($guard),
        };
        $this->assertInstanceOf(ClassificationPrompt::class, $promptBuilder);

        config(['ai.prompt.version' => 'optimized']);
        $promptBuilder = match (config('ai.prompt.version')) {
            'optimized' => new \App\Services\Ai\OptimizedPrompt($guard),
            default => new \App\Services\Ai\ClassificationPrompt($guard),
        };
        $this->assertInstanceOf(OptimizedPrompt::class, $promptBuilder);
    }

    public function test_optimized_prompt_generates_same_fields()
    {
        $ticket = [
            'issue_key' => 'DEMO-001',
            'summary' => 'Cannot access account',
            'description' => 'User reports login issues',
            'reporter' => 'john@example.com'
        ];

        $original = $this->originalPrompt->build($ticket);
        $optimized = $this->optimizedPrompt->build($ticket);

        // Both should contain the same ticket data
        $this->assertStringContainsString('Issue Key: DEMO-001', $original);
        $this->assertStringContainsString('Issue Key: DEMO-001', $optimized);
        $this->assertStringContainsString('Summary: Cannot access account', $original);
        $this->assertStringContainsString('Summary: Cannot access account', $optimized);
        $this->assertStringContainsString('Description: User reports login issues', $original);
        $this->assertStringContainsString('Description: User reports login issues', $optimized);
        $this->assertStringContainsString('Reporter: john@example.com', $original);
        $this->assertStringContainsString('Reporter: john@example.com', $optimized);
    }
}