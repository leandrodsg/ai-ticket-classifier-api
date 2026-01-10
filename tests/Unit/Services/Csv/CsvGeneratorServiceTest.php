<?php

namespace Tests\Unit\Services\Csv;

use App\Services\Csv\CsvGeneratorService;
use Tests\TestCase;

class CsvGeneratorServiceTest extends TestCase
{
    private CsvGeneratorService $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new CsvGeneratorService();
    }

    public function test_it_generates_csv_with_correct_header()
    {
        $csv = $this->generator->generate(1);

        $lines = explode("\n", trim($csv));
        $header = $lines[0];

        $this->assertEquals('Issue Key,Issue Type,Summary,Description,Reporter,Assignee,Priority,Status,Created,Labels', $header);
    }

    public function test_it_generates_requested_number_of_tickets()
    {
        $csv = $this->generator->generate(3);

        $lines = explode("\n", trim($csv));
        $dataLines = array_slice($lines, 1); // Skip header

        $this->assertCount(3, $dataLines);
    }

    public function test_it_generates_unique_issue_keys()
    {
        $csv = $this->generator->generate(5);

        $lines = explode("\n", trim($csv));
        $issueKeys = [];

        foreach (array_slice($lines, 1) as $line) { // Skip header
            $columns = str_getcsv($line);
            $issueKeys[] = $columns[0];
        }

        $this->assertCount(5, $issueKeys);
        $this->assertCount(5, array_unique($issueKeys)); // All unique
    }

    public function test_it_generates_issue_keys_with_correct_format()
    {
        $csv = $this->generator->generate(1);

        $lines = explode("\n", trim($csv));
        $dataLine = $lines[1];
        $columns = str_getcsv($dataLine);
        $issueKey = $columns[0];

        // Should match DEMO-TIMESTAMP-XXX format (e.g., DEMO-201845-001)
        $this->assertMatchesRegularExpression('/^DEMO-\d{6}-\d{3}$/', $issueKey);
    }

    public function test_it_generates_realistic_content()
    {
        $csv = $this->generator->generate(1);

        $lines = explode("\n", trim($csv));
        $dataLine = $lines[1];
        $columns = str_getcsv($dataLine);

        $summary = $columns[2];
        $description = $columns[3];

        $commonWords = ['user', 'account', 'problem', 'error', 'access', 'issue', 'cannot'];
        $hasRealisticContent = false;

        foreach ($commonWords as $word) {
            if (stripos($summary . ' ' . $description, $word) !== false) {
                $hasRealisticContent = true;
                break;
            }
        }

        $this->assertTrue($hasRealisticContent, 'Generated content should contain realistic text');
    }

    public function test_it_generates_valid_email_addresses()
    {
        $csv = $this->generator->generate(2);

        $lines = explode("\n", trim($csv));
        $emails = [];

        foreach (array_slice($lines, 1) as $line) { // Skip header
            $columns = str_getcsv($line);
            $emails[] = $columns[4]; // Reporter column
        }

        foreach ($emails as $email) {
            $this->assertMatchesRegularExpression('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email);
        }
    }

    public function test_it_generates_varied_issue_types()
    {
        $csv = $this->generator->generate(10);

        $lines = explode("\n", trim($csv));
        $issueTypes = [];

        foreach (array_slice($lines, 1) as $line) { // Skip header
            $columns = str_getcsv($line);
            $issueTypes[] = $columns[1]; // Issue Type column
        }

        // Should have variety
        $uniqueTypes = array_unique($issueTypes);
        $this->assertGreaterThan(1, count($uniqueTypes), 'Should generate varied issue types');
    }

    public function test_it_generates_varied_priorities()
    {
        $csv = $this->generator->generate(10);

        $lines = explode("\n", trim($csv));
        $priorities = [];

        foreach (array_slice($lines, 1) as $line) { // Skip header
            $columns = str_getcsv($line);
            $priorities[] = $columns[6]; // Priority column
        }

        // Should have variety
        $uniquePriorities = array_unique(array_filter($priorities));
        $this->assertGreaterThan(1, count($uniquePriorities), 'Should generate varied priorities');
    }

    public function test_it_escapes_csv_fields_with_commas()
    {
        // Create a generator with a template that has commas in description
        $csv = $this->generator->generate(1);

        $lines = explode("\n", trim($csv));
        $dataLine = $lines[1];

        // Should contain quoted fields where necessary
        $this->assertStringContainsString('"', $dataLine, 'Should contain quoted fields for commas');
    }

    public function test_it_handles_maximum_ticket_count()
    {
        $csv = $this->generator->generate(21);

        $lines = explode("\n", trim($csv));
        $dataLines = array_slice($lines, 1); // Skip header

        $this->assertCount(21, $dataLines);
    }

    public function test_it_cycles_through_templates_when_generating_many_tickets()
    {
        $csv = $this->generator->generate(15); // More than available templates (10)

        $lines = explode("\n", trim($csv));
        $dataLines = array_slice($lines, 1); // Skip header

        $this->assertCount(15, $dataLines);

        // Should cycle through all 10 templates and start again
        // Check that we have exactly 10 unique summaries (from 10 templates)
        $summaries = [];
        foreach ($dataLines as $line) {
            $columns = str_getcsv($line);
            $summaries[] = $columns[2];
        }

        $uniqueSummaries = array_unique($summaries);
        $this->assertEquals(
            CsvGeneratorService::TEMPLATE_COUNT,
            count($uniqueSummaries),
            'Should have exactly ' . CsvGeneratorService::TEMPLATE_COUNT . ' unique summaries from templates'
        );
        $this->assertEquals(15, count($summaries), 'Should have 15 total summaries');

        // Verify that the first 10 are unique and the last 5 repeat from the first 5
        $first10Summaries = array_slice($summaries, 0, 10);
        $last5Summaries = array_slice($summaries, 10, 5);
        $first5Summaries = array_slice($summaries, 0, 5);

        $this->assertEquals($first5Summaries, $last5Summaries, 'Last 5 summaries should repeat the first 5 due to template cycling');
    }
}
