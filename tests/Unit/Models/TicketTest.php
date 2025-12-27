<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Ticket;
use App\Models\ClassificationJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TicketTest extends TestCase
{
    use RefreshDatabase;
    public function test_ticket_belongs_to_classification_job(): void
    {
        $job = ClassificationJob::factory()->create();
        $ticket = Ticket::factory()->create(['job_id' => $job->id]);

        $this->assertInstanceOf(BelongsTo::class, $ticket->job());
        $this->assertEquals($job->id, $ticket->job->id);
    }

    public function test_issue_key_accessor_returns_uppercase(): void
    {
        $ticket = Ticket::factory()->create(['issue_key' => 'demo-123']);
        
        $this->assertEquals('DEMO-123', $ticket->issue_key);
    }

    public function test_issue_key_mutator_stores_uppercase(): void
    {
        $ticket = new Ticket();
        $ticket->issue_key = 'demo-456';
        
        $this->assertEquals('DEMO-456', $ticket->issue_key);
    }

    public function test_summary_accessor_trims_text(): void
    {
        $ticket = Ticket::factory()->create(['summary' => '  Test Summary  ']);
        
        $this->assertEquals('Test Summary', $ticket->summary);
    }

    public function test_reporter_mutator_validates_email(): void
    {
        $ticket = new Ticket();
        
        $ticket->reporter = 'test@example.com';
        $this->assertEquals('test@example.com', $ticket->reporter);
        
        $this->expectException(\InvalidArgumentException::class);
        $ticket->reporter = 'invalid-email';
    }

    public function test_category_accessor_and_mutator(): void
    {
        $ticket = new Ticket();
        
        // Test mutator
        $ticket->category = 'technical';
        $this->assertEquals('Technical', $ticket->category);
        
        $ticket->category = 'invalid-category';
        $this->assertEquals('General', $ticket->category);
        
        // Test accessor
        $ticket = Ticket::factory()->create(['category' => 'Commercial']);
        $this->assertEquals('Commercial', $ticket->category);
    }

    public function test_sla_due_date_accessor(): void
    {
        $dueDate = now()->addDays(7);
        $ticket = Ticket::factory()->create(['sla_due_date' => $dueDate]);
        
        $this->assertInstanceOf(\Carbon\Carbon::class, $ticket->sla_due_date);
        $this->assertNotNull($ticket->sla_due_date);
    }

    public function test_is_overdue_attribute(): void
    {
        $overdueTicket = Ticket::factory()->create([
            'sla_due_date' => now()->subDays(1)
        ]);

        $futureTicket = Ticket::factory()->create([
            'sla_due_date' => now()->addDays(1)
        ]);

        $this->assertTrue($overdueTicket->is_overdue);
        $this->assertFalse($futureTicket->is_overdue);
    }

    public function test_description_mutator_strips_html_tags(): void
    {
        $ticket = new Ticket();
        
        // HTML tags must be removed
        $ticket->description = '<div>Test description</div>';
        $this->assertEquals('Test description', $ticket->description);
        
        $ticket->description = '<b>Bold</b> and <i>italic</i> text';
        $this->assertEquals('Bold and italic text', $ticket->description);
        
        // Multiple spaces must be trimmed
        $ticket->description = '  Spaced text  ';
        $this->assertEquals('Spaced text', $ticket->description);
    }

    public function test_sla_time_remaining_attribute(): void
    {
        $ticket = Ticket::factory()->create([
            'sla_due_date' => now()->addDays(2)
        ]);
        
        $this->assertIsString($ticket->sla_time_remaining);
        $this->assertStringContainsString('remaining', $ticket->sla_time_remaining);
    }

    public function test_priority_mutator_normalizes_and_validates(): void
    {
        $ticket = new Ticket();
        
        $ticket->priority = 'critical';
        $this->assertEquals('Critical', $ticket->priority);
        
        $ticket->priority = 'INVALID';
        $this->assertEquals('Medium', $ticket->priority);  // Default
    }

    public function test_impact_mutator_normalizes_and_validates(): void
    {
        $ticket = new Ticket();
        
        $ticket->impact = 'high';
        $this->assertEquals('High', $ticket->impact);
        
        $ticket->impact = 'INVALID';
        $this->assertEquals('Medium', $ticket->impact);
    }

    public function test_urgency_mutator_normalizes_and_validates(): void
    {
        $ticket = new Ticket();

        $ticket->urgency = 'high';
        $this->assertEquals('High', $ticket->urgency);

        $ticket->urgency = 'INVALID';
        $this->assertEquals('Medium', $ticket->urgency);
    }

    public function test_does_not_use_updated_at_timestamp(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assertNotNull($ticket->created_at);
        $this->assertObjectNotHasProperty('updated_at', $ticket);

        // Update must not add updated_at
        $ticket->priority = 'High';
        $ticket->save();

        $this->assertObjectNotHasProperty('updated_at', $ticket->fresh());
    }

    public function test_sentiment_mutator_normalizes_and_validates(): void
    {
        $ticket = new Ticket();
        
        // Valid values
        $ticket->sentiment = 'positive';
        $this->assertEquals('Positive', $ticket->sentiment);
        
        $ticket->sentiment = 'NEGATIVE';
        $this->assertEquals('Negative', $ticket->sentiment);
        
        $ticket->sentiment = 'neutral';
        $this->assertEquals('Neutral', $ticket->sentiment);
        
        // Invalid value → default
        $ticket->sentiment = 'happy';
        $this->assertEquals('Neutral', $ticket->sentiment);
        
        $ticket->sentiment = '';
        $this->assertEquals('Neutral', $ticket->sentiment);
    }

    public function test_reporter_mutator_normalizes_to_lowercase(): void
    {
        $ticket = new Ticket();
        
        // UPPERCASE → lowercase
        $ticket->reporter = 'USER@EXAMPLE.COM';
        $this->assertEquals('user@example.com', $ticket->reporter);
        
        // Mixed case → lowercase
        $ticket->reporter = 'User.Name@Example.Com';
        $this->assertEquals('user.name@example.com', $ticket->reporter);
        
        // Spaces must be removed
        $ticket->reporter = '  test@example.com  ';
        $this->assertEquals('test@example.com', $ticket->reporter);
    }

    public function test_summary_mutator_capitalizes_first_letter(): void
    {
        $ticket = new Ticket();
        
        $ticket->summary = 'lowercase summary';
        $this->assertEquals('Lowercase summary', $ticket->summary);
        
        $ticket->summary = 'UPPERCASE SUMMARY';
        $this->assertEquals('UPPERCASE SUMMARY', $ticket->summary);
        
        $ticket->summary = '  spaced summary  ';
        $this->assertEquals('Spaced summary', $ticket->summary);
    }

    public function test_sla_time_remaining_returns_null_when_no_due_date(): void
    {
        $ticket = Ticket::factory()->create(['sla_due_date' => null]);
        
        $this->assertNull($ticket->sla_time_remaining);
    }

    public function test_sla_time_remaining_returns_overdue_for_past_dates(): void
    {
        $ticket = Ticket::factory()->create([
            'sla_due_date' => now()->subDays(5)
        ]);
        
        $this->assertEquals('Overdue', $ticket->sla_time_remaining);
    }

    public function test_sla_time_remaining_human_readable_format(): void
    {
        $ticket = Ticket::factory()->create([
            'sla_due_date' => now()->addHours(2)
        ]);
        
        // Must contain "hours" or similar
        $this->assertMatchesRegularExpression('/\d+\s+(hour|minute)s?\s+remaining/', $ticket->sla_time_remaining);
    }

    public function test_is_overdue_returns_false_when_no_due_date(): void
    {
        $ticket = Ticket::factory()->create(['sla_due_date' => null]);
        
        $this->assertFalse($ticket->is_overdue);
    }
}
