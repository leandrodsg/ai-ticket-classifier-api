<?php

namespace Tests\Feature;

use App\Models\ClassificationJob;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketQueryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_404_when_job_not_found()
    {
        $response = $this->getJson('/api/tickets/non-existent-uuid');

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Job not found',
                'message' => 'No classification job exists with the provided ID'
            ]);
    }

    public function test_it_returns_pending_job_status()
    {
        $job = ClassificationJob::factory()->create([
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/tickets/{$job->id}");

        $response->assertStatus(200)
            ->assertJson([
                'session_id' => $job->id,
                'status' => 'pending',
            ])
            ->assertJsonMissing(['results', 'tickets']);
    }

    public function test_it_returns_processing_job_status()
    {
        $job = ClassificationJob::factory()->create([
            'status' => 'processing',
        ]);

        $response = $this->getJson("/api/tickets/{$job->id}");

        $response->assertStatus(200)
            ->assertJson([
                'session_id' => $job->id,
                'status' => 'processing',
            ])
            ->assertJsonMissing(['results', 'tickets']);
    }

    public function test_it_returns_completed_job_with_results()
    {
        $job = ClassificationJob::factory()->completed()->create();
        
        $ticket = Ticket::factory()->create([
            'job_id' => $job->id,
            'issue_key' => 'TEST-001',
            'summary' => 'Test ticket',
            'category' => 'Technical',
            'sentiment' => 'Neutral',
            'urgency' => 'Medium',
            'impact' => 'Medium',
            'priority' => 'Medium',
        ]);

        $response = $this->getJson("/api/tickets/{$job->id}");

        $response->assertStatus(200)
            ->assertJson([
                'session_id' => $job->id,
                'status' => 'completed',
            ])
            ->assertJsonStructure([
                'session_id',
                'status',
                'created_at',
                'completed_at',
                'results',
                'tickets' => [
                    '*' => [
                        'issue_key',
                        'summary',
                        'category',
                        'sentiment',
                        'urgency',
                        'impact',
                        'priority',
                        'sla_due_date',
                    ]
                ]
            ]);

        $this->assertEquals('TEST-001', $response->json('tickets.0.issue_key'));
        $this->assertEquals('Technical', $response->json('tickets.0.category'));
    }

    public function test_it_returns_failed_job_with_error()
    {
        $job = ClassificationJob::factory()->failed()->create();

        $response = $this->getJson("/api/tickets/{$job->id}");

        $response->assertStatus(200)
            ->assertJson([
                'session_id' => $job->id,
                'status' => 'failed',
            ])
            ->assertJsonStructure([
                'session_id',
                'status',
                'created_at',
                'completed_at',
                'error',
            ])
            ->assertJsonMissing(['tickets']);
    }

    public function test_it_returns_multiple_tickets_for_completed_job()
    {
        $job = ClassificationJob::factory()->completed()->create();
        
        Ticket::factory()->count(3)->create([
            'job_id' => $job->id,
        ]);

        $response = $this->getJson("/api/tickets/{$job->id}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'tickets');
    }

    public function test_it_returns_iso8601_timestamps()
    {
        $job = ClassificationJob::factory()->completed()->create();

        $response = $this->getJson("/api/tickets/{$job->id}");

        $response->assertStatus(200);
        
        $createdAt = $response->json('created_at');
        $completedAt = $response->json('completed_at');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $createdAt);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $completedAt);
    }

    public function test_it_handles_invalid_uuid_format()
    {
        $response = $this->getJson('/api/tickets/invalid-uuid-format');

        $response->assertStatus(404);
    }

    public function test_it_does_not_expose_sensitive_ticket_data()
    {
        $job = ClassificationJob::factory()->completed()->create();
        
        Ticket::factory()->create([
            'job_id' => $job->id,
            'reporter' => 'user@example.com',
            'description' => 'Sensitive information here',
        ]);

        $response = $this->getJson("/api/tickets/{$job->id}");

        $response->assertStatus(200)
            ->assertJsonMissing(['reporter', 'description']);
    }

    public function test_it_returns_tickets_with_all_classification_fields()
    {
        $job = ClassificationJob::factory()->completed()->create();
        
        Ticket::factory()->create([
            'job_id' => $job->id,
            'category' => 'Technical',
            'sentiment' => 'Negative',
            'urgency' => 'High',
            'impact' => 'High',
            'priority' => 'Critical',
        ]);

        $response = $this->getJson("/api/tickets/{$job->id}");

        $response->assertStatus(200);
        
        $ticket = $response->json('tickets.0');
        $this->assertEquals('Technical', $ticket['category']);
        $this->assertEquals('Negative', $ticket['sentiment']);
        $this->assertEquals('High', $ticket['urgency']);
        $this->assertEquals('High', $ticket['impact']);
        $this->assertEquals('Critical', $ticket['priority']);
    }

    public function test_it_includes_sla_due_date_when_available()
    {
        $job = ClassificationJob::factory()->completed()->create();
        
        $slaDate = now()->addHours(4);
        Ticket::factory()->create([
            'job_id' => $job->id,
            'sla_due_date' => $slaDate,
        ]);

        $response = $this->getJson("/api/tickets/{$job->id}");

        $response->assertStatus(200);
        
        $this->assertNotNull($response->json('tickets.0.sla_due_date'));
    }

    public function test_it_handles_null_sla_due_date()
    {
        $job = ClassificationJob::factory()->completed()->create();
        
        Ticket::factory()->create([
            'job_id' => $job->id,
            'sla_due_date' => null,
        ]);

        $response = $this->getJson("/api/tickets/{$job->id}");

        $response->assertStatus(200);
        
        $this->assertNull($response->json('tickets.0.sla_due_date'));
    }
}
