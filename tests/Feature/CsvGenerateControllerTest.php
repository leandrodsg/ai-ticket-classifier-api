<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CsvGenerateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test CSV signing key for HmacSignatureService
        config(['services.csv_signing_key' => 'test_csv_signing_key_for_feature_tests_123456789']);
    }

    public function test_it_returns_200_with_valid_csv_when_ticket_count_is_provided()
    {
        $response = $this->postJson('/api/csv/generate', [
            'ticket_count' => 5
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'csv_content',
                        'filename',
                        'metadata' => [
                            'version',
                            'timestamp',
                            'session_id',
                            'row_count',
                            'expires_at',
                            'signature'
                        ]
                    ]
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('tickets_template.csv', $response->json('data.filename'));
        $this->assertEquals(5, $response->json('data.metadata.row_count'));
        $this->assertEquals('v1', $response->json('data.metadata.version'));
    }

    public function test_it_returns_base64_encoded_csv_content()
    {
        $response = $this->postJson('/api/csv/generate', [
            'ticket_count' => 1
        ]);

        $response->assertStatus(200);

        $csvContent = $response->json('data.csv_content');

        // Should be valid base64
        $this->assertTrue(base64_decode($csvContent, true) !== false);

        // Decoded content should contain CSV header
        $decoded = base64_decode($csvContent);
        $this->assertStringContainsString('Issue Key,Issue Type,Summary,Description,Reporter', $decoded);
    }

    public function test_it_generates_csv_with_correct_number_of_tickets()
    {
        $response = $this->postJson('/api/csv/generate', [
            'ticket_count' => 3
        ]);

        $response->assertStatus(200);

        $csvContent = base64_decode($response->json('data.csv_content'));
        $lines = explode("\n", trim($csvContent));

        // Should contain metadata section + header + 3 data rows
        // Metadata has ~9 lines, plus header + 3 data rows = ~13 lines total
        $this->assertGreaterThan(10, count($lines)); // Should have many lines including metadata
        $this->assertEquals(3, $response->json('data.metadata.row_count'));

        // Count actual data rows (after metadata)
        $dataLines = 0;
        $inDataSection = false;
        foreach ($lines as $line) {
            if (str_contains($line, '# END METADATA')) {
                $inDataSection = true;
                continue;
            }
            if ($inDataSection && !empty(trim($line)) && !str_starts_with(trim($line), '#')) {
                $dataLines++;
            }
        }

        // Should have header + 3 data rows = 4 data lines
        $this->assertEquals(4, $dataLines);
    }

    public function test_it_returns_422_when_ticket_count_is_missing()
    {
        $response = $this->postJson('/api/csv/generate', []);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'error' => 'validation_error',
                    'message' => 'Validation failed',
                ]);

        $this->assertArrayHasKey('ticket_count', $response->json('details'));
    }

    public function test_it_returns_422_when_ticket_count_is_too_low()
    {
        $response = $this->postJson('/api/csv/generate', [
            'ticket_count' => 0
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'error' => 'validation_error',
                    'message' => 'Validation failed',
                ]);
    }

    public function test_it_returns_422_when_ticket_count_is_too_high()
    {
        $response = $this->postJson('/api/csv/generate', [
            'ticket_count' => 51
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'error' => 'validation_error',
                    'message' => 'Validation failed',
                ]);
    }

    public function test_it_returns_422_when_ticket_count_is_not_integer()
    {
        $response = $this->postJson('/api/csv/generate', [
            'ticket_count' => 'invalid'
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'error' => 'validation_error',
                    'message' => 'Validation failed',
                ]);
    }

    public function test_it_generates_valid_metadata_structure()
    {
        $response = $this->postJson('/api/csv/generate', [
            'ticket_count' => 2
        ]);

        $response->assertStatus(200);

        $metadata = $response->json('data.metadata');

        // Check version
        $this->assertEquals('v1', $metadata['version']);

        // Check timestamp format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|\+\d{2}:\d{2})?$/', $metadata['timestamp']);

        // Check session_id is UUID
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $metadata['session_id']);

        // Check row_count matches request
        $this->assertEquals(2, $metadata['row_count']);

        // Check expires_at is in future
        $expiresAt = \Carbon\Carbon::parse($metadata['expires_at']);
        $this->assertTrue($expiresAt->isAfter(now()));

        // Check signature is 64-char hex
        $this->assertEquals(64, strlen($metadata['signature']));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $metadata['signature']);
    }

    public function test_it_generates_csv_with_metadata_section()
    {
        $response = $this->postJson('/api/csv/generate', [
            'ticket_count' => 1
        ]);

        $response->assertStatus(200);

        $csvContent = base64_decode($response->json('data.csv_content'));

        // Should contain metadata section
        $this->assertStringStartsWith('# METADATA - DO NOT EDIT THIS SECTION', $csvContent);
        $this->assertStringContainsString('# version: v1', $csvContent);
        $this->assertStringContainsString('# signature:', $csvContent);
        $this->assertStringContainsString('# timestamp:', $csvContent);
        $this->assertStringContainsString('# session_id:', $csvContent);
        $this->assertStringContainsString('# row_count: 1', $csvContent);
        $this->assertStringContainsString('# nonce:', $csvContent);
        $this->assertStringContainsString('# expires_at:', $csvContent);
        $this->assertStringContainsString('# END METADATA', $csvContent);

        // Should contain CSV data after metadata
        $this->assertStringContainsString("\n\nIssue Key,Issue Type,Summary,Description,Reporter", $csvContent);
    }

    public function test_it_handles_maximum_allowed_ticket_count()
    {
        $response = $this->postJson('/api/csv/generate', [
            'ticket_count' => 50
        ]);

        $response->assertStatus(200);
        $this->assertEquals(50, $response->json('data.metadata.row_count'));
    }

    public function test_it_handles_minimum_allowed_ticket_count()
    {
        $response = $this->postJson('/api/csv/generate', [
            'ticket_count' => 1
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.metadata.row_count'));
    }

    public function test_it_generates_different_session_ids_for_different_requests()
    {
        $response1 = $this->postJson('/api/csv/generate', ['ticket_count' => 1]);
        $response2 = $this->postJson('/api/csv/generate', ['ticket_count' => 1]);

        $sessionId1 = $response1->json('data.metadata.session_id');
        $sessionId2 = $response2->json('data.metadata.session_id');

        $this->assertNotEquals($sessionId1, $sessionId2);
    }

    public function test_it_accepts_additional_parameters_without_error()
    {
        $response = $this->postJson('/api/csv/generate', [
            'ticket_count' => 3,
            'extra_param' => 'ignored'
        ]);

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('data.metadata.row_count'));
    }

    public function test_it_enforces_rate_limiting_on_csv_generate_endpoint()
    {
        // Clear cache to start fresh
        Cache::flush();

        // Make 10 requests (the limit for generate endpoint: 10 requests per minute)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/csv/generate', ['ticket_count' => 1]);

            // All should succeed
            $response->assertStatus(200);

            // Verify rate limit headers
            $response->assertHeader('X-RateLimit-Limit', '10');
            $response->assertHeader('X-RateLimit-Remaining', (string)(9 - $i));
        }

        // 11th request should be rate limited (exceeded 10/min limit)
        $response = $this->postJson('/api/csv/generate', ['ticket_count' => 1]);

        $response->assertStatus(429)
                ->assertJson([
                    'error' => 'rate_limit_exceeded',
                    'message' => 'Too many requests. Please try again later.',
                ])
                ->assertHeader('X-RateLimit-Limit', '10')
                ->assertHeader('X-RateLimit-Remaining', '0');
    }

    public function test_it_does_not_expose_nonce_in_response()
    {
        $response = $this->postJson('/api/csv/generate', ['ticket_count' => 1]);

        $response->assertStatus(200);

        $metadata = $response->json('data.metadata');

        // Nonce should NOT be in response metadata (security)
        $this->assertArrayNotHasKey('nonce', $metadata);

        // But nonce SHOULD be in the CSV itself
        $csvContent = base64_decode($response->json('data.csv_content'));
        $this->assertStringContainsString('# nonce:', $csvContent);
    }
}
