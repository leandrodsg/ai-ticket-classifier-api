<?php

namespace App\Services\Csv;

class CsvParser
{
    /**
     * Parse complete CSV content and return structured data
     */
    public function parse(string $csvContent): array
    {
        $lines = $this->splitLines($csvContent);
        $metadata = $this->extractMetadata($lines);
        $dataRows = $this->extractDataRows($lines);

        return [
            'metadata' => $metadata,
            'data_rows' => $dataRows,
            'row_count' => count($dataRows),
        ];
    }

    /**
     * Extract metadata section from CSV lines
     */
    public function extractMetadata(array $lines): array
    {
        $metadata = [];
        $inMetadata = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '# METADATA - DO NOT EDIT THIS SECTION') {
                $inMetadata = true;
                continue;
            }

            if ($line === '# END METADATA') {
                break;
            }

            if ($inMetadata && str_starts_with($line, '# ') && !str_starts_with($line, '# METADATA')) {
                $parts = explode(': ', substr($line, 2), 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    $metadata[$key] = $value;
                }
            }
        }

        return $metadata;
    }

    /**
     * Extract data rows from CSV lines (excluding metadata and header)
     */
    public function extractDataRows(array $lines): array
    {
        $dataRows = [];
        $inMetadata = false;
        $pastMetadata = false;
        $foundHeader = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '# METADATA - DO NOT EDIT THIS SECTION') {
                $inMetadata = true;
                continue;
            }

            if ($line === '# END METADATA') {
                $inMetadata = false;
                $pastMetadata = true;
                continue;
            }

            if ($inMetadata) {
                continue;
            }

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            if ($pastMetadata && !$foundHeader) {
                // This should be the header row - skip it
                $foundHeader = true;
                continue;
            }

            if ($pastMetadata && $foundHeader) {
                // This is a data row
                $row = str_getcsv($line, ',', '"', '\\');
                if (count($row) >= 9) { // Minimum expected columns
                    $dataRows[] = [
                        'issue_key' => $this->sanitizeField($row[0] ?? ''),
                        'issue_type' => $this->sanitizeField($row[1] ?? ''),
                        'summary' => $this->sanitizeField($row[2] ?? ''),
                        'description' => $this->sanitizeField($row[3] ?? ''),
                        'reporter' => $this->sanitizeField($row[4] ?? ''),
                        'assignee' => $this->sanitizeField($row[5] ?? ''),
                        'priority' => $this->sanitizeField($row[6] ?? ''),
                        'status' => $this->sanitizeField($row[7] ?? ''),
                        'created' => $this->sanitizeField($row[8] ?? ''),
                        'labels' => $this->sanitizeField($row[9] ?? ''),
                    ];
                }
            }
        }

        return $dataRows;
    }

    /**
     * Count data rows (ignore metadata, header, empty lines)
     */
    public function countRows(array $lines): int
    {
        $dataRows = $this->extractDataRows($lines);
        return count($dataRows);
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

    /**
     * Sanitize a field value (basic trimming)
     */
    private function sanitizeField(string $value): string
    {
        return trim($value);
    }
}
