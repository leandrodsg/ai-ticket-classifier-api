<?php

namespace Tests\Unit\Services\Csv;

use App\Services\Csv\CsvValidator;
use Tests\TestCase;

class CsvValidatorTest extends TestCase
{
    private CsvValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new CsvValidator();
    }

    /** @test */
    public function it_validates_schema_with_valid_rows()
    {
        $rows = [
            [
                'issue_key' => 'PROJ-123',
                'summary' => 'Valid summary text',
                'description' => 'This is a valid description with enough characters.',
                'reporter' => 'user@example.com'
            ],
            [
                'issue_key' => 'DEMO-001',
                'summary' => 'Another valid summary',
                'description' => 'Another valid description that meets requirements.',
                'reporter' => 'test@example.com'
            ]
        ];

        $result = $this->validator->validateSchema($rows);
        $this->assertTrue($result);
    }

    /** @test */
    public function it_rejects_schema_with_invalid_rows()
    {
        $rows = [
            [
                'issue_key' => 'PROJ-123',
                'summary' => 'Valid',
                'description' => 'Valid description',
                'reporter' => 'user@example.com'
            ],
            [
                'issue_key' => 'INVALID',
                'summary' => 'Valid summary',
                'description' => 'Valid description',
                'reporter' => 'user@example.com'
            ]
        ];

        $result = $this->validator->validateSchema($rows);
        $this->assertFalse($result);
    }

    /** @test */
    public function it_validates_row_with_all_valid_fields()
    {
        $row = [
            'issue_key' => 'PROJ-123',
            'summary' => 'Valid summary text here',
            'description' => 'This is a valid description with enough characters for testing.',
            'reporter' => 'user@example.com',
            'assignee' => 'assignee@example.com',
            'priority' => 'High',
            'status' => 'Open',
            'created' => '2025-12-26T10:00:00Z',
            'labels' => 'bug,urgent'
        ];

        $errors = $this->validator->validateRow($row);
        $this->assertEmpty($errors);
    }

    /** @test */
    public function it_detects_missing_required_fields()
    {
        $row = [
            'issue_key' => 'PROJ-123',
            // missing summary
            'description' => 'Valid description',
            'reporter' => 'user@example.com'
        ];

        $errors = $this->validator->validateRow($row);
        $this->assertContains("Field 'summary' is required", $errors);
    }

    /** @test */
    public function it_validates_issue_key_format()
    {
        // Valid
        $error = $this->validator->validateField('issue_key', 'PROJ-123');
        $this->assertNull($error);

        // Invalid format
        $error = $this->validator->validateField('issue_key', 'INVALID');
        $this->assertEquals('Issue key must be alphanumeric with hyphen format (e.g., PROJ-123)', $error);

        // Too long
        $error = $this->validator->validateField('issue_key', 'VERY-LONG-PROJECT-NAME-123');
        $this->assertEquals('Issue key cannot exceed 20 characters', $error);
    }

    /** @test */
    public function it_validates_summary_length()
    {
        // Valid
        $error = $this->validator->validateField('summary', 'Valid summary text');
        $this->assertNull($error);

        // Too short
        $error = $this->validator->validateField('summary', 'Hi');
        $this->assertEquals('Summary must be at least 5 characters long', $error);

        // Too long
        $longSummary = str_repeat('A', 201);
        $error = $this->validator->validateField('summary', $longSummary);
        $this->assertEquals('Summary cannot exceed 200 characters', $error);
    }

    /** @test */
    public function it_validates_description_length()
    {
        // Valid
        $error = $this->validator->validateField('description', 'This is a valid description with enough characters.');
        $this->assertNull($error);

        // Too short
        $error = $this->validator->validateField('description', 'Short');
        $this->assertEquals('Description must be at least 10 characters long', $error);

        // Too long
        $longDesc = str_repeat('A', 2001);
        $error = $this->validator->validateField('description', $longDesc);
        $this->assertEquals('Description cannot exceed 2000 characters', $error);
    }

    /** @test */
    public function it_validates_email_format()
    {
        // Valid reporter
        $error = $this->validator->validateField('reporter', 'user@example.com');
        $this->assertNull($error);

        // Valid assignee
        $error = $this->validator->validateField('assignee', 'assignee@company.com');
        $this->assertNull($error);

        // Invalid format
        $error = $this->validator->validateField('reporter', 'invalid-email');
        $this->assertEquals('Reporter must be a valid email address', $error);

        // Too long
        $longEmail = str_repeat('a', 256) . '@example.com';
        $error = $this->validator->validateField('reporter', $longEmail);
        $this->assertEquals('Reporter email cannot exceed 255 characters', $error);
    }

    /** @test */
    public function it_blocks_disposable_emails()
    {
        $disposableEmails = [
            'user@temp-mail.org',
            'test@10minutemail.com',
            'demo@guerrillamail.com',
            'fake@mailinator.com'
        ];

        foreach ($disposableEmails as $email) {
            $error = $this->validator->validateField('reporter', $email);
            $this->assertEquals('Disposable email addresses are not allowed', $error);
        }
    }

    /** @test */
    public function it_allows_plus_addressing()
    {
        $error = $this->validator->validateField('reporter', 'user+tag@example.com');
        $this->assertNull($error);
    }

    /** @test */
    public function it_validates_priority_enum()
    {
        // Valid priorities
        $validPriorities = ['Critical', 'High', 'Medium', 'Low'];
        foreach ($validPriorities as $priority) {
            $error = $this->validator->validateField('priority', $priority);
            $this->assertNull($error);
        }

        // Invalid priority
        $error = $this->validator->validateField('priority', 'Urgent');
        $this->assertEquals('Priority must be one of: Critical, High, Medium, Low', $error);
    }

    /** @test */
    public function it_validates_status_enum()
    {
        // Valid statuses
        $validStatuses = ['Open', 'In Progress', 'Resolved', 'Closed'];
        foreach ($validStatuses as $status) {
            $error = $this->validator->validateField('status', $status);
            $this->assertNull($error);
        }

        // Invalid status
        $error = $this->validator->validateField('status', 'Pending');
        $this->assertEquals('Status must be one of: Open, In Progress, Resolved, Closed', $error);
    }

    /** @test */
    public function it_validates_created_timestamp()
    {
        // Valid ISO 8601
        $error = $this->validator->validateField('created', '2025-12-26T10:00:00Z');
        $this->assertNull($error);

        $error = $this->validator->validateField('created', '2025-12-26T10:00:00+01:00');
        $this->assertNull($error);

        // Invalid format
        $error = $this->validator->validateField('created', 'not-a-date');
        $this->assertEquals('Created date must be a valid ISO 8601 timestamp', $error);

        // Future date
        $future = date('Y-m-d\TH:i:s\Z', strtotime('+1 day'));
        $error = $this->validator->validateField('created', $future);
        $this->assertEquals('Created date cannot be in the future', $error);
    }

    /** @test */
    public function it_validates_labels_format()
    {
        // Valid labels
        $error = $this->validator->validateField('labels', 'bug,urgent,high-priority');
        $this->assertNull($error);

        $error = $this->validator->validateField('labels', 'label with spaces');
        $this->assertNull($error);

        // Too long
        $longLabels = str_repeat('a', 256);
        $error = $this->validator->validateField('labels', $longLabels);
        $this->assertEquals('Labels cannot exceed 255 characters', $error);

        // Invalid characters
        $error = $this->validator->validateField('labels', 'bug;urgent@invalid');
        $this->assertEquals('Labels must contain only letters, numbers, semicolons, commas, hyphens, and spaces', $error);
    }

    /** @test */
    public function it_handles_optional_assignee()
    {
        // Valid assignee
        $row = [
            'issue_key' => 'PROJ-123',
            'summary' => 'Valid summary',
            'description' => 'Valid description',
            'reporter' => 'user@example.com',
            'assignee' => 'assignee@example.com'
        ];
        $errors = $this->validator->validateRow($row);
        $this->assertEmpty($errors);

        // Missing assignee (should be valid)
        unset($row['assignee']);
        $errors = $this->validator->validateRow($row);
        $this->assertEmpty($errors);

        // Invalid assignee
        $row['assignee'] = 'invalid-email';
        $errors = $this->validator->validateRow($row);
        $this->assertContains('Assignee must be a valid email address', $errors);
    }

    /** @test */
    public function it_returns_multiple_errors_for_invalid_row()
    {
        $row = [
            'issue_key' => 'INVALID',
            'summary' => 'Hi', // Too short
            'description' => 'Short', // Too short
            'reporter' => 'invalid-email',
            'assignee' => 'also-invalid'
        ];

        $errors = $this->validator->validateRow($row);

        $this->assertContains('Issue key must be alphanumeric with hyphen format (e.g., PROJ-123)', $errors);
        $this->assertContains('Summary must be at least 5 characters long', $errors);
        $this->assertContains('Description must be at least 10 characters long', $errors);
        $this->assertContains('Reporter must be a valid email address', $errors);
        $this->assertContains('Assignee must be a valid email address', $errors);
    }
}
