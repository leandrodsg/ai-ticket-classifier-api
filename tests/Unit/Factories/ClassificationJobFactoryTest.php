<?php

namespace Tests\Unit\Factories;

use Tests\TestCase;
use App\Models\ClassificationJob;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ClassificationJobFactoryTest extends TestCase
{
    use RefreshDatabase;
    public function test_classification_job_factory_creates_valid_data(): void
    {
        $job = ClassificationJob::factory()->create();

        $this->assertInstanceOf(ClassificationJob::class, $job);
        $this->assertNotNull($job->id);
        $this->assertNotNull($job->session_id);
        $this->assertNotNull($job->status);
        $this->assertNotNull($job->total_tickets);
        $this->assertNotNull($job->processed_tickets);
        $this->assertIsArray($job->results);
        $this->assertNotNull($job->created_at);
    }

    public function test_pending_state(): void
    {
        $job = ClassificationJob::factory()->pending()->create();

        $this->assertEquals('pending', $job->status);
        $this->assertEquals(0, $job->processed_tickets);
        $this->assertNull($job->processing_time_ms);
        $this->assertNull($job->completed_at);
        $this->assertEquals([], $job->results);
    }

    public function test_completed_state(): void
    {
        $job = ClassificationJob::factory()->completed()->create();

        $this->assertEquals('completed', $job->status);
        $this->assertEquals($job->total_tickets, $job->processed_tickets);
        $this->assertNotNull($job->processing_time_ms);
        $this->assertNotNull($job->completed_at);
        $this->assertIsArray($job->results);
    }

    public function test_failed_state(): void
    {
        $job = ClassificationJob::factory()->failed()->create();

        $this->assertEquals('failed', $job->status);
        $this->assertNotNull($job->processing_time_ms);
        $this->assertIsArray($job->results);
        $this->assertArrayHasKey('error', $job->results);
    }

    public function test_session_id_is_uuid(): void
    {
        $job = ClassificationJob::factory()->create();

        $this->assertTrue(Str::isUuid($job->session_id));
    }

    public function test_results_structure_for_completed_job(): void
    {
        $job = ClassificationJob::factory()->completed()->create();

        $this->assertIsArray($job->results);
        
        if (count($job->results) > 0) {
            $result = $job->results[0];
            $this->assertArrayHasKey('issue_key', $result);
            $this->assertArrayHasKey('category', $result);
            $this->assertArrayHasKey('sentiment', $result);
            $this->assertArrayHasKey('priority', $result);
            $this->assertArrayHasKey('impact', $result);
            $this->assertArrayHasKey('urgency', $result);
        }
    }
}
