<?php

namespace Tests\Feature;

use App\Models\ClassificationJob;
use App\Models\Ticket;
use App\Services\Ai\AiClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClassificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $apiKey = config('services.openrouter.api_key') ?: env('OPENROUTER_API_KEY');
        if (!$apiKey) {
            $this->markTestSkipped('OpenRouter API key not configured');
        }
    }

    public function test_successful_ticket_classification()
    {
        set_time_limit(60);

        $csvContent = $this->generateValidCsv([
            [
                'DEMO-001',
                'Support',
                'Cannot access my account',
                'User reports they cannot log in after password reset. Error: Invalid credentials.',
                'john.doe@example.com',
                'support@example.com',
                'High',
                'Open',
                '2025-12-27T10:00:00Z',
                'account;login'
            ]
        ]);

        $response = $this->postJson('/api/classify', [
            'csv_content' => $csvContent,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'session_id',
                'metadata' => [
                    'total_tickets',
                    'processed_tickets',
                    'processing_time_ms',
                ],
                'tickets' => [
                    '*' => [
                        'issue_key',
                        'summary',
                        'description',
                        'reporter',
                        'classification' => [
                            'category',
                            'sentiment',
                            'priority',
                            'impact',
                            'urgency',
                            'sla_due_date',
                            'reasoning',
                        ],
                    ],
                ],
            ]);

        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['metadata']['total_tickets']);
        $this->assertEquals(1, $data['metadata']['processed_tickets']);
        $this->assertGreaterThan(0, $data['metadata']['processing_time_ms']);

        $ticket = $data['tickets'][0];
        $this->assertEquals('DEMO-001', $ticket['issue_key']);
        $this->assertContains($ticket['classification']['category'], [
            'Technical', 'Commercial', 'Billing', 'General', 'Support'
        ]);
        $this->assertContains($ticket['classification']['sentiment'], [
            'Positive', 'Negative', 'Neutral'
        ]);
        $this->assertContains($ticket['classification']['priority'], [
            'Critical', 'High', 'Medium', 'Low'
        ]);
        $this->assertContains($ticket['classification']['impact'], [
            'High', 'Medium', 'Low'
        ]);
        $this->assertContains($ticket['classification']['urgency'], [
            'High', 'Medium', 'Low'
        ]);
        $this->assertNotEmpty($ticket['classification']['reasoning']);

        $this->assertDatabaseHas('classification_jobs', [
            'session_id' => $data['session_id'],
            'status' => 'completed',
            'total_tickets' => 1,
            'processed_tickets' => 1,
        ]);

        $this->assertDatabaseHas('tickets', [
            'issue_key' => 'DEMO-001',
            'summary' => 'Cannot access my account',
            'reporter' => 'john.doe@example.com',
        ]);
    }

    public function test_multiple_tickets_classification()
    {
        set_time_limit(90);

        $csvContent = $this->generateValidCsv([
            [
                'DEMO-001',
                'Support',
                'Cannot access account',
                'User cannot log in after password reset. Error message shown.',
                'john@example.com',
                'support@example.com',
                'High',
                'Open',
                '2025-12-27T10:00:00Z',
                'account'
            ],
            [
                'DEMO-002',
                'Bug',
                'Payment fails',
                'Credit card payment processing returns error 500 for amounts over $1000.',
                'jane@example.com',
                'billing@example.com',
                'Critical',
                'Open',
                '2025-12-27T11:00:00Z',
                'billing;payment'
            ],
        ]);

        $response = $this->postJson('/api/classify', [
            'csv_content' => $csvContent,
        ]);

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(2, $data['metadata']['total_tickets']);
        $this->assertEquals(2, $data['metadata']['processed_tickets']);
        $this->assertCount(2, $data['tickets']);

        $this->assertEquals('DEMO-001', $data['tickets'][0]['issue_key']);
        $this->assertEquals('DEMO-002', $data['tickets'][1]['issue_key']);

        // Should create 1 job with 2 tickets, not 2 jobs
        $this->assertEquals(1, ClassificationJob::count());
        $this->assertEquals(2, Ticket::count());
    }

    public function test_missing_csv_content_returns_validation_error()
    {
        $response = $this->postJson('/api/classify', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'Validation failed',
            ]);
    }

    public function test_empty_csv_returns_error()
    {
        $csvContent = $this->generateValidCsv([]);

        $response = $this->postJson('/api/classify', [
            'csv_content' => $csvContent,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'No valid tickets found in CSV',
            ]);
    }

    public function test_too_many_tickets_returns_error()
    {
        $tickets = [];
        for ($i = 1; $i <= 21; $i++) {
            $tickets[] = [
                "DEMO-{$i}",
                'Support',
                'Test ticket',
                'This is a test ticket description for testing purposes.',
                "user{$i}@example.com",
                'support@example.com',
                'Medium',
                'Open',
                '2025-12-27T10:00:00Z',
                'test'
            ];
        }

        $csvContent = $this->generateValidCsv($tickets);

        $response = $this->postJson('/api/classify', [
            'csv_content' => $csvContent,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'Maximum 20 tickets allowed per request',
            ]);
    }

    public function test_missing_required_fields_returns_error()
    {
        $csvContent = $this->generateValidCsv([
            [
                'DEMO-001',
                'Support',
                '', // missing summary
                'Description here',
                'john@example.com',
                'support@example.com',
                'High',
                'Open',
                '2025-12-27T10:00:00Z',
                'test'
            ]
        ]);

        $response = $this->postJson('/api/classify', [
            'csv_content' => $csvContent,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'Validation error',
            ]);
    }

    public function test_invalid_email_returns_error()
    {
        $csvContent = $this->generateValidCsv([
            [
                'DEMO-001',
                'Support',
                'Test ticket',
                'This is a test ticket description.',
                'invalid-email', // invalid email
                'support@example.com',
                'High',
                'Open',
                '2025-12-27T10:00:00Z',
                'test'
            ]
        ]);

        $response = $this->postJson('/api/classify', [
            'csv_content' => $csvContent,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'Validation error',
            ]);
    }

    public function test_itil_priority_calculation_is_correct()
    {
        set_time_limit(60);

        $csvContent = $this->generateValidCsv([
            [
                'DEMO-001',
                'Support',
                'Critical system outage',
                'Production database is down affecting all users. Immediate attention required.',
                'admin@example.com',
                'support@example.com',
                'High',
                'Open',
                '2025-12-27T10:00:00Z',
                'critical;database'
            ]
        ]);

        $response = $this->postJson('/api/classify', [
            'csv_content' => $csvContent,
        ]);

        $response->assertStatus(200);

        $data = $response->json();
        $ticket = $data['tickets'][0];

        $impact = $ticket['classification']['impact'];
        $urgency = $ticket['classification']['urgency'];
        $priority = $ticket['classification']['priority'];

        if ($impact === 'High' && $urgency === 'High') {
            $this->assertEquals('Critical', $priority);
        } elseif ($impact === 'High' && $urgency === 'Medium') {
            $this->assertEquals('High', $priority);
        } elseif ($impact === 'Medium' && $urgency === 'High') {
            $this->assertEquals('High', $priority);
        }

        $this->assertNotEmpty($ticket['classification']['sla_due_date']);
    }

    public function test_sla_calculation_is_based_on_priority()
    {
        set_time_limit(60);

        $csvContent = $this->generateValidCsv([
            [
                'DEMO-001',
                'Support',
                'System completely down',
                'All services are offline. Multiple customers affected. Revenue impact severe.',
                'ops@example.com',
                'support@example.com',
                'Critical',
                'Open',
                '2025-12-27T10:00:00Z',
                'critical'
            ]
        ]);

        $response = $this->postJson('/api/classify', [
            'csv_content' => $csvContent,
        ]);

        $response->assertStatus(200);

        $data = $response->json();
        $ticket = $data['tickets'][0];

        $classification = $ticket['classification'];
        $slaDueDate = \Carbon\Carbon::parse($classification['sla_due_date']);
        $now = \Carbon\Carbon::now();

        if ($classification['priority'] === 'Critical') {
            $this->assertLessThanOrEqual(1, $now->diffInHours($slaDueDate));
        } elseif ($classification['priority'] === 'High') {
            $this->assertLessThanOrEqual(4, $now->diffInHours($slaDueDate));
        } elseif ($classification['priority'] === 'Medium') {
            $this->assertLessThanOrEqual(48, $now->diffInHours($slaDueDate));
        } elseif ($classification['priority'] === 'Low') {
            $this->assertLessThanOrEqual(168, $now->diffInHours($slaDueDate));
        }
    }

    public function test_database_transaction_on_failure()
    {
        $initialJobCount = ClassificationJob::count();
        $initialTicketCount = Ticket::count();

        // Mock AiClassificationService to throw exception
        $this->mock(AiClassificationService::class, function ($mock) {
            $mock->shouldReceive('classify')
                ->andThrow(new \Exception('AI service error'));
        });

        $csvContent = $this->generateValidCsv([
            [
                'DEMO-001',
                'Support',
                'Test ticket',
                'Test description',
                'test@example.com',
                'support@example.com',
                'High',
                'Open',
                '2025-12-27T10:00:00Z',
                'test'
            ]
        ]);

        $response = $this->postJson('/api/classify', [
            'csv_content' => $csvContent,
        ]);

        $response->assertStatus(500);

        $this->assertEquals($initialJobCount, ClassificationJob::count());
        $this->assertEquals($initialTicketCount, Ticket::count());
    }

    private function generateValidCsv(array $tickets): string
    {
        $csv = "# METADATA - DO NOT EDIT THIS SECTION\n";
        $csv .= "# version: v1\n";
        $csv .= "# signature: test_signature\n";
        $csv .= "# timestamp: " . now()->toIso8601String() . "\n";
        $csv .= "# session_id: " . \Illuminate\Support\Str::uuid() . "\n";
        $csv .= "# row_count: " . count($tickets) . "\n";
        $csv .= "# nonce: " . \Illuminate\Support\Str::random(32) . "\n";
        $csv .= "# expires_at: " . now()->addHour()->toIso8601String() . "\n";
        $csv .= "# END METADATA\n";
        $csv .= "\n";
        $csv .= "Issue Key,Issue Type,Summary,Description,Reporter,Assignee,Priority,Status,Created,Labels\n";

        foreach ($tickets as $ticket) {
            $csv .= implode(',', array_map(function ($field) {
                if (strpos($field, ',') !== false || strpos($field, '"') !== false) {
                    return '"' . str_replace('"', '""', $field) . '"';
                }
                return $field;
            }, $ticket)) . "\n";
        }

        return $csv;
    }
}
