<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\ClassificationJob;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ClassificationJobTest extends TestCase
{
    use RefreshDatabase;
    public function test_classification_job_has_many_tickets(): void
    {
        $job = ClassificationJob::factory()->create();
        $ticket = Ticket::factory()->create(['job_id' => $job->id]);

        $this->assertInstanceOf(HasMany::class, $job->tickets());
        $this->assertTrue($job->tickets->contains($ticket));
    }

    public function test_pending_scope(): void
    {
        $pendingJob = ClassificationJob::factory()->pending()->create();
        ClassificationJob::factory()->completed()->create();

        $pendingJobs = ClassificationJob::pending()->get();

        $this->assertCount(1, $pendingJobs);
        $this->assertEquals($pendingJob->id, $pendingJobs->first()->id);
    }

    public function test_completed_scope(): void
    {
        $completedJob = ClassificationJob::factory()->completed()->create();
        ClassificationJob::factory()->pending()->create();

        $completedJobs = ClassificationJob::completed()->get();

        $this->assertCount(1, $completedJobs);
        $this->assertEquals($completedJob->id, $completedJobs->first()->id);
    }

    public function test_failed_scope(): void
    {
        ClassificationJob::factory()->pending()->create();
        ClassificationJob::factory()->completed()->create();
        ClassificationJob::factory()->failed()->create();

        $failedJobs = ClassificationJob::failed()->get();

        $this->assertCount(1, $failedJobs);
        $this->assertEquals('failed', $failedJobs->first()->status);
    }

    public function test_uses_uuid_as_primary_key(): void
    {
        $job = ClassificationJob::factory()->create();
        
        // Must be valid UUID format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $job->id
        );
        
        // Must not be auto-increment integer
        $this->assertIsString($job->id);
    }

    public function test_results_casts_to_array(): void
    {
        $results = [
            ['ticket' => 'TEST-1', 'category' => 'Technical'],
            ['ticket' => 'TEST-2', 'category' => 'Billing'],
        ];
        
        $job = ClassificationJob::factory()->create([
            'results' => $results
        ]);
        
        // Must return array, not JSON string
        $this->assertIsArray($job->results);
        $this->assertCount(2, $job->results);
        $this->assertEquals('Technical', $job->results[0]['category']);
        
        // Must save as JSON in DB but return as array
        $fresh = $job->fresh();
        $this->assertIsArray($fresh->results);
    }

    public function test_fillable_fields_can_be_mass_assigned(): void
    {
        $data = [
            'session_id' => 'test-session',
            'status' => 'pending',
            'total_tickets' => 10,
            'processed_tickets' => 0,
            'results' => ['test' => 'data'],
            'processing_time_ms' => 1000,
            'completed_at' => now(),
        ];
        
        $job = ClassificationJob::create($data);
        
        $this->assertEquals('test-session', $job->session_id);
        $this->assertEquals('pending', $job->status);
        $this->assertEquals(10, $job->total_tickets);
        $this->assertIsArray($job->results);
    }

    public function test_non_fillable_fields_are_guarded(): void
    {
        $job = ClassificationJob::create([
            'id' => 'forced-id',  // Não está em fillable
            'session_id' => 'test-session',
            'status' => 'completed',
        ]);
        
        // ID must be generated, not what we passed
        $this->assertNotEquals('forced-id', $job->id);
    }

    public function test_datetime_fields_cast_to_carbon(): void
    {
        $job = ClassificationJob::factory()->completed()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $job->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $job->completed_at);
    }

    public function test_completed_at_is_nullable(): void
    {
        $job = ClassificationJob::factory()->pending()->create();

        $this->assertNull($job->completed_at);
    }

    public function test_does_not_use_updated_at_timestamp(): void
    {
        $job = ClassificationJob::factory()->create();
        
        $this->assertNotNull($job->created_at);
        $this->assertObjectNotHasProperty('updated_at', $job);
        
        // Update must not add updated_at
        $job->status = 'completed';
        $job->save();
        
        $this->assertObjectNotHasProperty('updated_at', $job->fresh());
    }

    public function test_can_eager_load_tickets(): void
    {
        $job = ClassificationJob::factory()->create();
        Ticket::factory()->count(5)->create(['job_id' => $job->id]);
        
        // Eager load
        $jobWithTickets = ClassificationJob::with('tickets')->find($job->id);
        
        $this->assertCount(5, $jobWithTickets->tickets);
        $this->assertTrue($jobWithTickets->relationLoaded('tickets'));
    }

    public function test_prevents_n_plus_one_queries(): void
    {
        // Create 3 jobs with tickets
        $jobs = ClassificationJob::factory()->count(3)->create();
        foreach ($jobs as $job) {
            Ticket::factory()->count(2)->create(['job_id' => $job->id]);
        }
        
        \DB::enableQueryLog();
        
        // Eager load to avoid N+1
        $loadedJobs = ClassificationJob::with('tickets')->get();
        foreach ($loadedJobs as $job) {
            $count = $job->tickets->count();  // Must not trigger query
        }
        
        $queries = \DB::getQueryLog();
        
        // Must have only 2 queries: select jobs + select tickets
        $this->assertLessThanOrEqual(2, count($queries));
        
        \DB::disableQueryLog();
    }
}
