<?php

namespace Tests\Unit\Services\Csv;

use App\Services\Csv\CsvMetadataGenerator;
use App\Services\Security\HmacSignatureService;
use Tests\TestCase;

class CsvMetadataGeneratorTest extends TestCase
{
    private CsvMetadataGenerator $metadataGenerator;
    private $hmacService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock HmacSignatureService to avoid needing CSV_SIGNING_KEY in tests
        $this->hmacService = \Mockery::mock(HmacSignatureService::class);
        $this->hmacService->shouldReceive('generate')
            ->andReturn('a1b2c3d4e5f67890123456789012345678901234567890123456789012345678');

        $this->metadataGenerator = new CsvMetadataGenerator($this->hmacService);
    }

    /** @test */
    public function it_adds_metadata_to_csv_content()
    {
        $csvContent = "Issue Key,Summary,Description,Reporter\nDEMO-001,Test,Description,test@example.com\n";

        $result = $this->metadataGenerator->addMetadata($csvContent);

        $this->assertStringStartsWith('# METADATA - DO NOT EDIT THIS SECTION', $result);
        $this->assertStringContainsString('# version: v1', $result);
        $this->assertStringContainsString('# signature:', $result);
        $this->assertStringContainsString('# timestamp:', $result);
        $this->assertStringContainsString('# session_id:', $result);
        $this->assertStringContainsString('# row_count: 1', $result);
        $this->assertStringContainsString('# nonce:', $result);
        $this->assertStringContainsString('# expires_at:', $result);
        $this->assertStringContainsString('# END METADATA', $result);
        $this->assertStringContainsString($csvContent, $result);
    }

    /** @test */
    public function it_generates_valid_uuid_for_session_id()
    {
        $csvContent = "Issue Key,Summary,Description,Reporter\nDEMO-001,Test,Description,test@example.com\n";

        $result = $this->metadataGenerator->addMetadata($csvContent);

        // Extract session_id from metadata
        preg_match('/# session_id: ([a-f0-9-]+)\n/', $result, $matches);
        $sessionId = $matches[1];

        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $sessionId);
    }

    /** @test */
    public function it_generates_32_character_nonce()
    {
        $csvContent = "Issue Key,Summary,Description,Reporter\nDEMO-001,Test,Description,test@example.com\n";

        $result = $this->metadataGenerator->addMetadata($csvContent);

        // Extract nonce from metadata
        preg_match('/# nonce: ([a-zA-Z0-9]+)\n/', $result, $matches);
        $nonce = $matches[1];

        $this->assertEquals(32, strlen($nonce));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $nonce);
    }

    /** @test */
    public function it_counts_data_rows_correctly()
    {
        $csvContent = "Issue Key,Summary,Description,Reporter\nDEMO-001,Test 1,Description 1,test1@example.com\nDEMO-002,Test 2,Description 2,test2@example.com\nDEMO-003,Test 3,Description 3,test3@example.com\n";

        $result = $this->metadataGenerator->addMetadata($csvContent);

        $this->assertStringContainsString('# row_count: 3', $result);
    }

    /** @test */
    public function it_counts_data_rows_ignoring_empty_lines()
    {
        $csvContent = "Issue Key,Summary,Description,Reporter\nDEMO-001,Test 1,Description 1,test1@example.com\n\nDEMO-002,Test 2,Description 2,test2@example.com\n";

        $result = $this->metadataGenerator->addMetadata($csvContent);

        $this->assertStringContainsString('# row_count: 2', $result);
    }

    /** @test */
    public function it_generates_iso8601_timestamp()
    {
        $csvContent = "Issue Key,Summary,Description,Reporter\nDEMO-001,Test,Description,test@example.com\n";

        $result = $this->metadataGenerator->addMetadata($csvContent);

        // Extract timestamp from metadata
        preg_match('/# timestamp: ([^\n]+)\n/', $result, $matches);
        $timestamp = $matches[1];

        // Should match ISO 8601 format (with or without Z)
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|\+\d{2}:\d{2})?$/', $timestamp);
    }

    /** @test */
    public function it_generates_expires_at_one_hour_from_now()
    {
        $csvContent = "Issue Key,Summary,Description,Reporter\nDEMO-001,Test,Description,test@example.com\n";

        $before = now();
        $result = $this->metadataGenerator->addMetadata($csvContent);
        $after = now();

        // Extract expires_at from metadata
        preg_match('/# expires_at: ([^\n]+)\n/', $result, $matches);
        $expiresAt = $matches[1];

        $expiresDateTime = \Carbon\Carbon::parse($expiresAt);

        // Should be approximately 1 hour from now
        $expectedExpiry = $before->copy()->addHour();
        $diffInMinutes = abs($expectedExpiry->diffInMinutes($expiresDateTime));

        $this->assertLessThan(2, $diffInMinutes, 'Expiry should be approximately 1 hour from generation time');
    }

    /** @test */
    public function it_generates_valid_hmac_signature()
    {
        $csvContent = "Issue Key,Summary,Description,Reporter\nDEMO-001,Test,Description,test@example.com\n";

        $result = $this->metadataGenerator->addMetadata($csvContent);

        // Extract signature from metadata
        preg_match('/# signature: ([a-f0-9]+)\n/', $result, $matches);
        $signature = $matches[1];

        // Should be 64-character hex string (SHA-256)
        $this->assertEquals(64, strlen($signature));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);

        // Verify signature is valid
        $metadata = $this->extractMetadataFromResult($result);
        $expectedSignature = $this->hmacService->generate([
            'version' => $metadata['version'],
            'timestamp' => $metadata['timestamp'],
            'session_id' => $metadata['session_id'],
            'row_count' => $metadata['row_count'],
            'nonce' => $metadata['nonce'],
        ]);

        $this->assertEquals($expectedSignature, $signature);
    }

    /** @test */
    public function it_preserves_original_csv_content()
    {
        $originalCsv = "Issue Key,Summary,Description,Reporter\nDEMO-001,Test,Description,test@example.com\n";

        $result = $this->metadataGenerator->addMetadata($originalCsv);

        $this->assertStringContainsString($originalCsv, $result);
    }

    /** @test */
    public function it_adds_blank_line_after_metadata()
    {
        $csvContent = "Issue Key,Summary,Description,Reporter\nDEMO-001,Test,Description,test@example.com\n";

        $result = $this->metadataGenerator->addMetadata($csvContent);

        // Should have blank line between metadata and CSV content
        $this->assertStringContainsString("# END METADATA\n\nIssue Key", $result);
    }

    /** @test */
    public function it_handles_csv_with_no_data_rows()
    {
        $csvContent = "Issue Key,Summary,Description,Reporter\n";

        $result = $this->metadataGenerator->addMetadata($csvContent);

        $this->assertStringContainsString('# row_count: 0', $result);
    }

    /** @test */
    public function it_handles_large_csv_content()
    {
        $csvContent = "Issue Key,Summary,Description,Reporter\n";
        for ($i = 1; $i <= 50; $i++) {
            $csvContent .= sprintf("DEMO-%03d,Test %d,Description %d,test%d@example.com\n", $i, $i, $i, $i);
        }

        $result = $this->metadataGenerator->addMetadata($csvContent);

        $this->assertStringContainsString('# row_count: 50', $result);
    }

    /** @test */
    public function it_extracts_metadata_correctly()
    {
        $csvContent = "Issue Key,Summary\nDEMO-001,Test\n";
        $csvWithMetadata = $this->metadataGenerator->addMetadata($csvContent);

        // Extract using PUBLIC method
        $metadata = $this->metadataGenerator->extractMetadata($csvWithMetadata);

        $this->assertArrayHasKey('version', $metadata);
        $this->assertEquals('v1', $metadata['version']);
        $this->assertArrayHasKey('signature', $metadata);
        $this->assertArrayHasKey('timestamp', $metadata);
        $this->assertArrayHasKey('session_id', $metadata);
        $this->assertArrayHasKey('row_count', $metadata);
        $this->assertArrayHasKey('nonce', $metadata);
        $this->assertArrayHasKey('expires_at', $metadata);

        // Verify signature is 64-char hex
        $this->assertEquals(64, strlen($metadata['signature']));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $metadata['signature']);

        // Verify UUID format
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $metadata['session_id']);
    }

    /** @test */
    public function it_handles_malformed_metadata_gracefully()
    {
        $badCsv = "# METADATA\n# broken: metadata\nIssue Key\n";

        $metadata = $this->metadataGenerator->extractMetadata($badCsv);

        // Should return array even if malformed
        $this->assertIsArray($metadata);
        // Should not crash, even with malformed data
    }

    /**
     * Helper method to extract metadata from generated result
     */
    private function extractMetadataFromResult(string $result): array
    {
        $lines = explode("\n", $result);
        $metadata = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '# END METADATA') {
                break;
            }

            if (str_starts_with($line, '# ') && !str_starts_with($line, '# METADATA')) {
                [$key, $value] = explode(': ', substr($line, 2), 2);
                $metadata[trim($key)] = trim($value);
            }
        }

        return $metadata;
    }
}
