<?php

namespace App\Services\Csv;

class CsvSanitizer
{
    /**
     * Sanitize complete CSV content for injection prevention
     */
    public function sanitize(string $csvContent): string
    {
        $lines = $this->splitLines($csvContent);
        $sanitizedLines = [];

        foreach ($lines as $line) {
            // Don't sanitize metadata lines
            if (str_starts_with(trim($line), '#')) {
                $sanitizedLines[] = $line;
                continue;
            }

            // Sanitize data lines
            $sanitizedLines[] = $this->sanitizeLine($line);
        }

        return implode("\n", $sanitizedLines);
    }

    /**
     * Remove formula injection characters from a value
     */
    public function removeFormulaChars(string $value): string
    {
        // Remove dangerous characters from start of cell that could be interpreted as formulas
        // This prevents CSV injection attacks like =SUM(A1:A10) or +AVERAGE(B1:B5)
        $value = preg_replace('/^[=+\-@\t\r]+/', '', $value);

        return $value;
    }

    /**
     * Escape quotes in CSV field
     */
    public function escapeQuotes(string $value): string
    {
        return str_replace('"', '""', $value);
    }

    /**
     * Wrap value in quotes if it contains special characters
     */
    public function wrapInQuotes(string $value): string
    {
        // Check if value contains comma, newline, or quote
        if (preg_match('/[,"\\n\\r]/', $value)) {
            return '"' . $value . '"';
        }

        return $value;
    }

    /**
     * Sanitize a single CSV line
     */
    private function sanitizeLine(string $line): string
    {
        if (empty(trim($line))) {
            return $line;
        }

        $fields = str_getcsv($line);
        $sanitizedFields = [];

        foreach ($fields as $field) {
            $sanitized = $this->sanitizeField($field);
            $sanitizedFields[] = $sanitized;
        }

        // Rebuild CSV line
        $csvLine = '';
        foreach ($sanitizedFields as $field) {
            if ($csvLine !== '') {
                $csvLine .= ',';
            }
            $csvLine .= $field;
        }

        return $csvLine;
    }

    /**
     * Sanitize a single field value
     */
    private function sanitizeField(string $value): string
    {
        // Step 1: Remove formula injection characters
        $value = $this->removeFormulaChars($value);

        // Step 2: Escape quotes
        $value = $this->escapeQuotes($value);

        // Step 3: Wrap in quotes if needed
        $value = $this->wrapInQuotes($value);

        return $value;
    }

    /**
     * Split CSV content into lines, handling different line endings
     */
    private function splitLines(string $csvContent): array
    {
        // Normalize line endings to \n
        $normalized = str_replace(["\r\n", "\r"], "\n", $csvContent);
        return explode("\n", $normalized);
    }
}
