<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\ClassificationPrompt;
use App\Services\Ai\OptimizedPrompt;
use App\Services\Security\PromptInjectionGuard;
use Tests\TestCase;

class PromptComparisonTest extends TestCase
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

    public function test_percentage_token_reduction()
    {
        $ticket = [
            'issue_key' => 'DEMO-001',
            'summary' => 'Cannot access my account after password reset',
            'description' => 'User reports they cannot log in to their account after password reset. The system shows error 403.',
            'reporter' => 'john.doe@example.com'
        ];

        $original = $this->originalPrompt->build($ticket);
        $optimized = $this->optimizedPrompt->build($ticket);

        $originalTokens = strlen($original) / 4;
        $optimizedTokens = strlen($optimized) / 4;
        $reductionPercent = ($originalTokens - $optimizedTokens) / $originalTokens * 100;

        $this->assertGreaterThanOrEqual(40, $reductionPercent);
        $this->assertLessThanOrEqual(60, $reductionPercent);
        $this->assertLessThanOrEqual(700, $optimizedTokens);
    }

    public function test_both_generate_valid_classification()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Login problem',
            'description' => 'Cannot access dashboard',
            'reporter' => 'user@example.com'
        ];

        $original = $this->originalPrompt->build($ticket);
        $optimized = $this->optimizedPrompt->build($ticket);

        // Both should be valid prompts (contain required elements)
        $this->assertStringContainsString('JSON', $original);
        $this->assertStringContainsString('JSON', $optimized);
        $this->assertStringContainsString('category', $original);
        $this->assertStringContainsString('category', $optimized);
        $this->assertStringContainsString('Technical', $original);
        $this->assertStringContainsString('Technical', $optimized);
    }

    public function test_compact_examples_work()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test',
            'description' => 'Test description',
            'reporter' => 'test@example.com'
        ];

        $optimized = $this->optimizedPrompt->build($ticket);

        // Should contain at least some examples but be compact
        $this->assertStringContainsString('Technical', $optimized);
        $this->assertStringContainsString('High', $optimized);

        // Should be shorter than original
        $original = $this->originalPrompt->build($ticket);
        $this->assertLessThan(strlen($original), strlen($optimized));
    }
}