<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\ClassificationPrompt;
use App\Services\Security\PromptInjectionGuard;
use Tests\TestCase;

class ClassificationPromptTest extends TestCase
{
    private ClassificationPrompt $promptBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $guard = new PromptInjectionGuard();
        $this->promptBuilder = new ClassificationPrompt($guard);
    }

    public function test_build_creates_valid_prompt()
    {
        $ticket = [
            'issue_key' => 'DEMO-001',
            'summary' => 'Cannot access account',
            'description' => 'User reports login issues',
            'reporter' => 'john@example.com'
        ];

        $prompt = $this->promptBuilder->build($ticket);

        $this->assertIsString($prompt);
        $this->assertStringContainsString('SYSTEM INSTRUCTIONS', $prompt);
        $this->assertStringContainsString('Issue Key: DEMO-001', $prompt);
        $this->assertStringContainsString('Summary: Cannot access account', $prompt);
        $this->assertStringContainsString('Description: User reports login issues', $prompt);
        $this->assertStringContainsString('Reporter: john@example.com', $prompt);
    }

    public function test_prompt_includes_instructions()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test summary',
            'description' => 'Test description',
            'reporter' => 'test@example.com'
        ];

        $prompt = $this->promptBuilder->build($ticket);

        $this->assertStringContainsString('CLASSIFICATION TASK', $prompt);
        $this->assertStringContainsString('STRICT RULES', $prompt);
        $this->assertStringContainsString('SECURITY NOTICE', $prompt);
        $this->assertStringContainsString('Brief explanation', $prompt);
    }

    public function test_prompt_includes_valid_options()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test summary',
            'description' => 'Test description',
            'reporter' => 'test@example.com'
        ];

        $prompt = $this->promptBuilder->build($ticket);

        $this->assertStringContainsString('Technical, Commercial, Billing, General, Support', $prompt);
        $this->assertStringContainsString('High, Medium, Low', $prompt);
        $this->assertStringContainsString('ITIL methodology', $prompt);
    }

    public function test_prompt_requests_json_response()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Test summary',
            'description' => 'Test description',
            'reporter' => 'test@example.com'
        ];

        $prompt = $this->promptBuilder->build($ticket);

        $this->assertStringContainsString('OUTPUT FORMAT', $prompt);
        $this->assertStringContainsString('"category":', $prompt);
        $this->assertStringContainsString('"sentiment":', $prompt);
        $this->assertStringContainsString('"impact":', $prompt);
        $this->assertStringContainsString('"urgency":', $prompt);
        $this->assertStringContainsString('"reasoning":', $prompt);
    }

    public function test_prompt_handles_special_characters()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => 'Summary with "quotes" and \'apostrophes\'',
            'description' => 'Description with special chars: @#$%^&*()',
            'reporter' => 'user+tag@example.com'
        ];

        $prompt = $this->promptBuilder->build($ticket);

        $this->assertStringContainsString('Summary with "quotes" and \'apostrophes\'', $prompt);
        $this->assertStringContainsString('Description with special chars: @#$%^&*()', $prompt);
        $this->assertStringContainsString('Reporter: user+tag@example.com', $prompt);
    }
}
