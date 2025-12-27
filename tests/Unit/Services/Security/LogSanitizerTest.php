<?php

namespace Tests\Unit\Services\Security;

use App\Services\Security\LogSanitizer;
use Tests\TestCase;

class LogSanitizerTest extends TestCase
{
    public function test_sanitize_ticket_truncates_summary()
    {
        $ticket = [
            'issue_key' => 'TEST-001',
            'summary' => str_repeat('A', 200),
            'description' => 'Short description',
            'reporter' => 'user@example.com',
        ];

        $result = LogSanitizer::sanitizeTicket($ticket);

        $this->assertEquals(103, strlen($result['summary'])); // 100 + '...'
        $this->assertStringEndsWith('...', $result['summary']);
    }

    public function test_sanitize_ticket_masks_email()
    {
        $ticket = [
            'issue_key' => 'TEST-002',
            'summary' => 'Test',
            'description' => 'Test',
            'reporter' => 'john.doe@example.com',
        ];

        $result = LogSanitizer::sanitizeTicket($ticket);

        $this->assertNotEquals('john.doe@example.com', $result['reporter']);
        $this->assertStringContainsString('@example.com', $result['reporter']);
        $this->assertStringContainsString('*', $result['reporter']);
    }

    public function test_sanitize_ticket_does_not_log_full_description()
    {
        $ticket = [
            'issue_key' => 'TEST-003',
            'summary' => 'Test',
            'description' => 'This is a sensitive description with PII data',
            'reporter' => 'user@example.com',
        ];

        $result = LogSanitizer::sanitizeTicket($ticket);

        $this->assertArrayNotHasKey('description', $result);
        $this->assertArrayHasKey('description_length', $result);
        $this->assertEquals(strlen($ticket['description']), $result['description_length']);
    }

    public function test_mask_email_preserves_domain()
    {
        $email = 'john.doe@example.com';
        $masked = LogSanitizer::maskEmail($email);

        $this->assertStringContainsString('@example.com', $masked);
        $this->assertStringContainsString('*', $masked);
        $this->assertNotEquals($email, $masked);
    }

    public function test_mask_email_handles_short_emails()
    {
        $email = 'ab@example.com';
        $masked = LogSanitizer::maskEmail($email);

        $this->assertStringContainsString('*', $masked);
        $this->assertStringContainsString('@example.com', $masked);
    }

    public function test_mask_email_handles_invalid_email()
    {
        $invalid = 'not-an-email';
        $masked = LogSanitizer::maskEmail($invalid);

        $this->assertEquals($invalid, $masked);
    }

    public function test_truncate_respects_length()
    {
        $text = 'This is a long text that needs truncation';
        $truncated = LogSanitizer::truncate($text, 20);

        $this->assertEquals(23, strlen($truncated)); // 20 + '...'
        $this->assertStringEndsWith('...', $truncated);
    }

    public function test_truncate_does_not_modify_short_text()
    {
        $text = 'Short text';
        $truncated = LogSanitizer::truncate($text, 50);

        $this->assertEquals($text, $truncated);
    }

    public function test_sanitize_classification_truncates_reasoning()
    {
        $classification = [
            'category' => 'Technical',
            'sentiment' => 'Negative',
            'impact' => 'High',
            'urgency' => 'High',
            'reasoning' => str_repeat('This is a very long reasoning text. ', 20),
        ];

        $result = LogSanitizer::sanitizeClassification($classification);

        $this->assertLessThanOrEqual(203, strlen($result['reasoning'])); // 200 + '...'
        $this->assertStringEndsWith('...', $result['reasoning']);
    }
}
