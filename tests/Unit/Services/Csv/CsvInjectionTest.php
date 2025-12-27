<?php

namespace Tests\Unit\Services\Csv;

use App\Services\Csv\CsvSanitizer;
use Tests\TestCase;

class CsvInjectionTest extends TestCase
{
    private CsvSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new CsvSanitizer();
    }

    public function test_prevents_excel_formula_injection()
    {
        $malicious = '=SUM(A1:A10)';
        $safe = $this->sanitizer->removeFormulaChars($malicious);

        $this->assertStringStartsNotWith('=', $safe);
        $this->assertEquals('SUM(A1:A10)', $safe);
    }

    public function test_prevents_plus_formula_injection()
    {
        $malicious = "+cmd|'/c calc'!A1";
        $safe = $this->sanitizer->removeFormulaChars($malicious);

        $this->assertStringStartsNotWith('+', $safe);
    }

    public function test_prevents_at_symbol_injection()
    {
        $malicious = '@SUM(1+1)';
        $safe = $this->sanitizer->removeFormulaChars($malicious);

        $this->assertStringStartsNotWith('@', $safe);
        $this->assertEquals('SUM(1+1)', $safe);
    }

    public function test_prevents_minus_formula_injection()
    {
        $malicious = '-2+3';
        $safe = $this->sanitizer->removeFormulaChars($malicious);

        $this->assertStringStartsNotWith('-', $safe);
        $this->assertEquals('2+3', $safe);
    }

    public function test_prevents_tab_injection()
    {
        $malicious = "\t=1+1";
        $safe = $this->sanitizer->removeFormulaChars($malicious);

        $this->assertStringStartsNotWith("\t", $safe);
        $this->assertStringStartsNotWith('=', $safe);
    }

    public function test_escapes_quotes_correctly()
    {
        $input = 'Text with "quotes"';
        $escaped = $this->sanitizer->escapeQuotes($input);

        $this->assertEquals('Text with ""quotes""', $escaped);
    }

    public function test_wraps_comma_in_quotes()
    {
        $input = 'Text, with comma';
        $wrapped = $this->sanitizer->wrapInQuotes($input);

        $this->assertStringStartsWith('"', $wrapped);
        $this->assertStringEndsWith('"', $wrapped);
    }

    public function test_wraps_newline_in_quotes()
    {
        $input = "Text\nwith newline";
        $wrapped = $this->sanitizer->wrapInQuotes($input);

        $this->assertStringStartsWith('"', $wrapped);
        $this->assertStringEndsWith('"', $wrapped);
    }

    public function test_sanitize_complete_csv_line()
    {
        $csvLine = '=1+1,"normal text",@SUM(A1)';
        $sanitized = $this->sanitizer->sanitize($csvLine);

        // Should remove leading = and @
        $this->assertStringNotContainsString('=1+1', $sanitized);
        $this->assertStringNotContainsString('@SUM', $sanitized);
    }

    public function test_preserves_metadata_lines()
    {
        $metadataLine = '# This is a comment';
        $sanitized = $this->sanitizer->sanitize($metadataLine);

        $this->assertEquals($metadataLine, $sanitized);
    }

    public function test_multiple_injection_attempts()
    {
        $injections = [
            '=SUM(A1:A10)',
            "+cmd|'/c calc'!A1",
            '@SUM(1+1)',
            '-2+3',
            "\t=1+1",
            "\r=MALICIOUS()",
        ];

        foreach ($injections as $malicious) {
            $safe = $this->sanitizer->removeFormulaChars($malicious);
            
            $this->assertStringStartsNotWith('=', $safe);
            $this->assertStringStartsNotWith('+', $safe);
            $this->assertStringStartsNotWith('@', $safe);
            $this->assertStringStartsNotWith('-', $safe);
            $this->assertStringStartsNotWith("\t", $safe);
            $this->assertStringStartsNotWith("\r", $safe);
        }
    }
}
