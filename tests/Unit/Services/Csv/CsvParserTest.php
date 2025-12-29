<?php

namespace Tests\Unit\Services\Csv;

use App\Services\Csv\CsvParser;
use Tests\TestCase;

class CsvParserTest extends TestCase
{
    private CsvParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CsvParser();
    }

    /** @test */
    public function it_parses_valid_csv_correctly()
    {
        $csvContent = $this->getValidCsvContent();

        $result = $this->parser->parse($csvContent);

        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('data_rows', $result);
        $this->assertArrayHasKey('row_count', $result);

        $this->assertEquals(2, $result['row_count']);
        $this->assertCount(2, $result['data_rows']);
    }

    /** @test */
    public function it_extracts_metadata_section()
    {
        $csvContent = $this->getValidCsvContent();

        $metadata = $this->parser->extractMetadata(explode("\n", $csvContent));

        $this->assertEquals('v1', $metadata['version']);
        $this->assertEquals('a1b2c3d4e5f6...', $metadata['signature']);
        $this->assertEquals('2025-12-26T20:00:00Z', $metadata['timestamp']);
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $metadata['session_id']);
        $this->assertEquals('2', $metadata['row_count']);
        $this->assertEquals('a3c7b2f4d9e1a8b5c6d7e8f9', $metadata['nonce']);
        $this->assertEquals('2025-12-26T21:00:00Z', $metadata['expires_at']);
    }

    /** @test */
    public function it_extracts_data_rows_correctly()
    {
        $csvContent = $this->getValidCsvContent();

        $dataRows = $this->parser->extractDataRows(explode("\n", $csvContent));

        $this->assertCount(2, $dataRows);

        // Check first row
        $this->assertEquals('DEMO-001', $dataRows[0]['issue_key']);
        $this->assertEquals('Support', $dataRows[0]['issue_type']);
        $this->assertEquals('Cannot access my account', $dataRows[0]['summary']);
        $this->assertStringStartsWith('User reports they cannot log in', $dataRows[0]['description']);
        $this->assertEquals('john.doe@example.com', $dataRows[0]['reporter']);

        // Check second row
        $this->assertEquals('DEMO-002', $dataRows[1]['issue_key']);
        $this->assertEquals('Bug', $dataRows[1]['issue_type']);
    }

    /** @test */
    public function it_counts_rows_ignoring_metadata_header_and_empty_lines()
    {
        $csvContent = $this->getValidCsvContent();

        $count = $this->parser->countRows(explode("\n", $csvContent));

        $this->assertEquals(2, $count);
    }

    /** @test */
    public function it_handles_different_line_endings_crlf()
    {
        $csvContent = str_replace("\n", "\r\n", $this->getValidCsvContent());

        $result = $this->parser->parse($csvContent);

        $this->assertEquals(2, $result['row_count']);
    }

    /** @test */
    public function it_handles_utf8_with_accents()
    {
        $csvContent = "# METADATA - DO NOT EDIT THIS SECTION\n";
        $csvContent .= "# version: v1\n";
        $csvContent .= "# signature: test\n";
        $csvContent .= "# timestamp: 2025-12-26T20:00:00Z\n";
        $csvContent .= "# session_id: test\n";
        $csvContent .= "# row_count: 1\n";
        $csvContent .= "# nonce: test\n";
        $csvContent .= "# expires_at: 2025-12-26T21:00:00Z\n";
        $csvContent .= "# END METADATA\n\n";
        $csvContent .= "Issue Key,Issue Type,Summary,Description,Reporter,Assignee,Priority,Status,Created,Labels\n";
        $csvContent .= "DEMO-001,Support,Cannot access account,\"User reports login issues with special characters café\",user@example.com,,,,,\n";

        $result = $this->parser->parse($csvContent);

        $this->assertEquals(1, $result['row_count']);
        $this->assertEquals('Cannot access account', $result['data_rows'][0]['summary']);
        $this->assertStringContainsString('café', $result['data_rows'][0]['description']);
    }

    /** @test */
    public function it_handles_empty_metadata()
    {
        $csvContent = "Issue Key,Summary,Description,Reporter\n";
        $csvContent .= "DEMO-001,Test,Description,test@example.com\n";

        $metadata = $this->parser->extractMetadata(explode("\n", $csvContent));

        $this->assertEmpty($metadata);
    }

    /** @test */
    public function it_handles_no_data_rows()
    {
        $csvContent = "# METADATA - DO NOT EDIT THIS SECTION\n";
        $csvContent .= "# version: v1\n";
        $csvContent .= "# END METADATA\n\n";
        $csvContent .= "Issue Key,Issue Type,Summary,Description,Reporter\n";

        $dataRows = $this->parser->extractDataRows(explode("\n", $csvContent));

        $this->assertEmpty($dataRows);
    }

    /** @test */
    public function it_handles_malformed_metadata_lines()
    {
        $lines = [
            '# METADATA - DO NOT EDIT THIS SECTION',
            '# invalid line without colon',
            '# version: v1',
            '# END METADATA'
        ];

        $metadata = $this->parser->extractMetadata($lines);

        $this->assertEquals('v1', $metadata['version']);
        $this->assertArrayNotHasKey('invalid line without colon', $metadata);
    }

    private function getValidCsvContent(): string
    {
        return "# METADATA - DO NOT EDIT THIS SECTION\n" .
               "# version: v1\n" .
               "# signature: a1b2c3d4e5f6...\n" .
               "# timestamp: 2025-12-26T20:00:00Z\n" .
               "# session_id: 550e8400-e29b-41d4-a716-446655440000\n" .
               "# row_count: 2\n" .
               "# nonce: a3c7b2f4d9e1a8b5c6d7e8f9\n" .
               "# expires_at: 2025-12-26T21:00:00Z\n" .
               "# END METADATA\n\n" .
               "Issue Key,Issue Type,Summary,Description,Reporter,Assignee,Priority,Status,Created,Labels\n" .
               "DEMO-001,Support,Cannot access my account,\"User reports they cannot log in to their account after password reset. Error message: 'Invalid credentials'. User has tried multiple times.\",john.doe@example.com,,High,Open,2025-12-26T20:00:00Z,login;access\n" .
               "DEMO-002,Bug,Payment processing fails,\"Payment gateway returns error 500 when processing credit card payments over \$1000. Affects premium subscriptions.\",jane.smith@example.com,,High,Open,2025-12-26T20:15:00Z,payment;billing\n";
    }
}
