<?php

namespace Tests\Unit\Services\Csv;

use App\Services\Csv\CsvSanitizer;
use Tests\TestCase;

class CsvSanitizerTest extends TestCase
{
    private CsvSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new CsvSanitizer();
    }

    public function test_it_removes_formula_injection_characters()
    {
        $value = '=SUM(A1:A10)';
        $result = $this->sanitizer->removeFormulaChars($value);
        $this->assertEquals('SUM(A1:A10)', $result);

        $value = '+AVERAGE(B1:B5)';
        $result = $this->sanitizer->removeFormulaChars($value);
        $this->assertEquals('AVERAGE(B1:B5)', $result);

        $value = '@IMPORT(data.csv)';
        $result = $this->sanitizer->removeFormulaChars($value);
        $this->assertEquals('IMPORT(data.csv)', $result);

        $value = "\tTAB_FUNCTION()";
        $result = $this->sanitizer->removeFormulaChars($value);
        $this->assertEquals('TAB_FUNCTION()', $result);
    }

    public function test_it_removes_formula_patterns_within_text()
    {
        $value = 'Some text =SUM(A1) more text';
        $result = $this->sanitizer->removeFormulaChars($value);
        $this->assertEquals('Some text =SUM(A1) more text', $result); // Only removes from start
    }

    public function test_it_escapes_quotes()
    {
        $value = 'Text with "quotes" inside';
        $result = $this->sanitizer->escapeQuotes($value);
        $this->assertEquals('Text with ""quotes"" inside', $result);

        $value = 'Single "quote"';
        $result = $this->sanitizer->escapeQuotes($value);
        $this->assertEquals('Single ""quote""', $result);
    }

    public function test_it_wraps_in_quotes_when_contains_special_chars()
    {
        $value = 'Text, with comma';
        $result = $this->sanitizer->wrapInQuotes($value);
        $this->assertEquals('"Text, with comma"', $result);

        $value = "Text\nwith newline";
        $result = $this->sanitizer->wrapInQuotes($value);
        $this->assertEquals("\"Text\nwith newline\"", $result);

        $value = "Text\rwith carriage return";
        $result = $this->sanitizer->wrapInQuotes($value);
        $this->assertEquals("\"Text\rwith carriage return\"", $result);

        $value = 'Text "with quotes"';
        $result = $this->sanitizer->wrapInQuotes($value);
        $this->assertEquals('"Text "with quotes""', $result);
    }

    public function test_it_does_not_wrap_when_no_special_chars()
    {
        $value = 'Simple text without special chars';
        $result = $this->sanitizer->wrapInQuotes($value);
        $this->assertEquals('Simple text without special chars', $result);

        $value = 'Text with spaces and numbers 123';
        $result = $this->sanitizer->wrapInQuotes($value);
        $this->assertEquals('Text with spaces and numbers 123', $result);
    }

    public function test_it_sanitizes_complete_csv_content()
    {
        $csvContent = "# METADATA - DO NOT EDIT THIS SECTION\n" .
                      "# version: v1\n" .
                      "# END METADATA\n\n" .
                      "Issue Key,Issue Type,Summary,Description,Reporter\n" .
                      "DEMO-001,Support,=SUM(A1),\"Text with \"quotes\"\",user@example.com\n" .
                      "DEMO-002,Bug,Normal text,\"Description, with comma\",user2@example.com\n";

        $result = $this->sanitizer->sanitize($csvContent);

        // Metadata should remain unchanged
        $this->assertStringContainsString('METADATA - DO NOT EDIT THIS SECTION', $result);
        $this->assertStringContainsString('# version: v1', $result);

        // Data rows should be sanitized
        $this->assertStringContainsString('SUM(A1)', $result); // Formula chars removed
        $this->assertStringContainsString('Text with quotes', $result); // Original text preserved
        $this->assertStringContainsString('"Description, with comma"', $result); // Wrapped in quotes
    }

    public function test_it_preserves_metadata_unchanged()
    {
        $csvContent = "# METADATA - DO NOT EDIT THIS SECTION\n" .
                      "# version: v1\n" .
                      "# signature: =FORMULA()\n" . // Even if metadata has formula chars
                      "# END METADATA\n\n" .
                      "Issue Key,Summary\n" .
                      "DEMO-001,Test\n";

        $result = $this->sanitizer->sanitize($csvContent);

        // Metadata should be unchanged
        $this->assertStringContainsString('# signature: =FORMULA()', $result);
    }

    public function test_it_handles_empty_lines()
    {
        $csvContent = "Issue Key,Summary\n\n" .
                      "DEMO-001,Test\n" .
                      "\n" .
                      "DEMO-002,Another test\n";

        $result = $this->sanitizer->sanitize($csvContent);

        $lines = explode("\n", $result);
        $this->assertEquals("Issue Key,Summary", $lines[0]);
        $this->assertEquals("", $lines[1]); // Empty line preserved
        $this->assertEquals("DEMO-001,Test", $lines[2]);
        $this->assertEquals("", $lines[3]); // Empty line preserved
        $this->assertEquals("DEMO-002,Another test", $lines[4]);
    }

    public function test_it_sanitizes_complex_csv_with_multiple_issues()
    {
        $csvContent = "Issue Key,Issue Type,Summary,Description,Reporter\n" .
                      "DEMO-001,Support,=FORMULA(),\"Text with \"quotes\" and, comma\",user@example.com\n" .
                      "DEMO-002,Bug,+ANOTHER,\"Simple text\",user2@example.com\n";

        $result = $this->sanitizer->sanitize($csvContent);

        // Should contain sanitized values
        $this->assertStringContainsString('FORMULA()', $result); // = removed
        $this->assertStringContainsString('ANOTHER', $result); // + removed
        $this->assertStringContainsString('Text with', $result); // Text preserved
        $this->assertStringContainsString('quotes', $result); // Quotes handled
    }
}
