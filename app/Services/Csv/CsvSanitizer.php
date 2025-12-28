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
     * Sanitize a single field with multiple protection layers
     */
    private function sanitizeField(string $value): string
    {
        // Step 1: Remove formula injection characters
        $value = $this->removeFormulaChars($value);
        
        // Step 2: Remove control characters (NULL bytes, etc)
        $value = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $value);
        
        // Step 3: Limit length to prevent buffer overflow (max 5KB per field)
        $value = mb_substr($value, 0, 5000);
        
        // Step 4: Escape quotes
        $value = $this->escapeQuotes($value);
        
        // Step 5: Wrap in quotes if contains special characters
        if ($this->needsQuotes($value)) {
            $value = '"' . $value . '"';
        }
        
        return $value;
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
     * Public method for testing
     */
    public function wrapInQuotes(string $value): string
    {
        if ($this->needsQuotes($value)) {
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
     * Check if value needs to be wrapped in quotes
     */
    private function needsQuotes(string $value): bool
    {
        // Check if value contains comma, newline, or quote
        return preg_match('/[,"\n\r]/', $value) === 1;
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
