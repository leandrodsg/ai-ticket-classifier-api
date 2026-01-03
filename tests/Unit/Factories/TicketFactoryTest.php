<?php

namespace Tests\Unit\Factories;

use Tests\TestCase;
use App\Models\Ticket;
use App\Models\ClassificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TicketFactoryTest extends TestCase
{
    use RefreshDatabase;
    public function test_ticket_factory_creates_valid_data(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assertInstanceOf(Ticket::class, $ticket);
        $this->assertNotNull($ticket->id);
        $this->assertNotNull($ticket->issue_key);
        $this->assertNotNull($ticket->summary);
        $this->assertNotNull($ticket->description);
        $this->assertNotNull($ticket->reporter);
        $this->assertNotNull($ticket->category);
        $this->assertNotNull($ticket->sentiment);
        $this->assertNotNull($ticket->priority);
        $this->assertNotNull($ticket->impact);
        $this->assertNotNull($ticket->urgency);
        $this->assertNotNull($ticket->reasoning);
    }

    public function test_technical_state(): void
    {
        $ticket = Ticket::factory()->technical()->create();

        $this->assertEquals('Technical', $ticket->category);
    }

    public function test_commercial_state(): void
    {
        $ticket = Ticket::factory()->commercial()->create();

        $this->assertEquals('Commercial', $ticket->category);
    }

    public function test_billing_state(): void
    {
        $ticket = Ticket::factory()->billing()->create();

        $this->assertEquals('Billing', $ticket->category);
    }

    public function test_support_state(): void
    {
        $ticket = Ticket::factory()->support()->create();

        $this->assertEquals('Support', $ticket->category);
    }

    public function test_general_state(): void
    {
        $ticket = Ticket::factory()->general()->create();

        $this->assertEquals('General', $ticket->category);
    }

    public function test_issue_key_format(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assertMatchesRegularExpression('/^[A-Z]+-\d{4}$/', $ticket->issue_key);
    }

    public function test_reporter_is_valid_email(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assertIsString($ticket->reporter);
        $this->assertTrue(filter_var($ticket->reporter, FILTER_VALIDATE_EMAIL) !== false);
    }

    public function test_category_is_valid_enum(): void
    {
        $ticket = Ticket::factory()->create();

        $validCategories = ['Technical', 'Commercial', 'Billing', 'General', 'Support'];
        $this->assertContains($ticket->category, $validCategories);
    }

    public function test_sentiment_is_valid_enum(): void
    {
        $ticket = Ticket::factory()->create();

        $validSentiments = ['Positive', 'Negative', 'Neutral'];
        $this->assertContains($ticket->sentiment, $validSentiments);
    }

    public function test_priority_is_valid_enum(): void
    {
        $ticket = Ticket::factory()->create();

        $validPriorities = ['Critical', 'High', 'Medium', 'Low'];
        $this->assertContains($ticket->priority, $validPriorities);
    }

    public function test_has_classification_job_relationship(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assertInstanceOf(ClassificationJob::class, $ticket->job);
    }

    public function test_sla_due_date_is_datetime(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assertNotNull($ticket->sla_due_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $ticket->sla_due_date);
    }
}
