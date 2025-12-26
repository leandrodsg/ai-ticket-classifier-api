<?php

namespace App\Http\Middleware;

use App\Services\Csv\CsvParser;
use App\Services\Csv\CsvSanitizer;
use App\Services\Csv\CsvValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateCsvFormat
{
    private CsvParser $parser;
    private CsvSanitizer $sanitizer;
    private CsvValidator $validator;

    public function __construct(
        CsvParser $parser,
        CsvSanitizer $sanitizer,
        CsvValidator $validator
    ) {
        $this->parser = $parser;
        $this->sanitizer = $sanitizer;
        $this->validator = $validator;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only validate requests with CSV content
        if (!$request->has('csv_content')) {
            return $next($request);
        }

        $csvContent = $request->input('csv_content');

        // Basic validation
        if (!is_string($csvContent) || empty($csvContent)) {
            return response()->json([
                'success' => false,
                'error' => 'INVALID_CSV_FORMAT',
                'message' => 'CSV content must be a non-empty string'
            ], 422);
        }

        // Check file size (5MB limit)
        if (strlen($csvContent) > 5 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'error' => 'PAYLOAD_TOO_LARGE',
                'message' => 'CSV file size exceeds maximum limit of 5MB',
                'details' => [
                    'file_size' => strlen($csvContent),
                    'max_size' => 5 * 1024 * 1024
                ]
            ], 413);
        }

        try {
            // Parse CSV
            $parsed = $this->parser->parse($csvContent);

            // Validate metadata presence
            if (empty($parsed['metadata'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'INVALID_CSV_FORMAT',
                    'message' => 'CSV must contain metadata section'
                ], 422);
            }

            // Validate required metadata fields
            $requiredMetadata = ['version', 'signature', 'timestamp', 'session_id', 'row_count', 'nonce', 'expires_at'];
            foreach ($requiredMetadata as $field) {
                if (!isset($parsed['metadata'][$field]) || empty($parsed['metadata'][$field])) {
                    return response()->json([
                        'success' => false,
                        'error' => 'INVALID_CSV_FORMAT',
                        'message' => "CSV metadata missing required field: {$field}"
                    ], 422);
                }
            }

            // Validate version
            if ($parsed['metadata']['version'] !== 'v1') {
                return response()->json([
                    'success' => false,
                    'error' => 'INVALID_CSV_FORMAT',
                    'message' => 'CSV version must be v1'
                ], 422);
            }

            // Validate row count matches
            $actualRowCount = count($parsed['data_rows']);
            $expectedRowCount = (int) $parsed['metadata']['row_count'];

            if ($actualRowCount !== $expectedRowCount) {
                return response()->json([
                    'success' => false,
                    'error' => 'INVALID_CSV_FORMAT',
                    'message' => 'Row count in metadata does not match actual data rows',
                    'details' => [
                        'expected' => $expectedRowCount,
                        'actual' => $actualRowCount
                    ]
                ], 422);
            }

            // Validate minimum rows
            if ($actualRowCount < 1) {
                return response()->json([
                    'success' => false,
                    'error' => 'NO_DATA_ROWS',
                    'message' => 'CSV must contain at least one ticket data row'
                ], 422);
            }

            // Validate maximum rows
            if ($actualRowCount > 50) {
                return response()->json([
                    'success' => false,
                    'error' => 'TOO_MANY_ROWS',
                    'message' => 'CSV contains too many rows (maximum 50 per request)',
                    'details' => [
                        'row_count' => $actualRowCount,
                        'max_rows' => 50
                    ]
                ], 422);
            }

            // Validate data rows
            if (!$this->validator->validateSchema($parsed['data_rows'])) {
                $errors = [];
                foreach ($parsed['data_rows'] as $index => $row) {
                    $rowErrors = $this->validator->validateRow($row);
                    if (!empty($rowErrors)) {
                        $errors["row_" . ($index + 1)] = $rowErrors;
                    }
                }

                return response()->json([
                    'success' => false,
                    'error' => 'VALIDATION_FAILED',
                    'message' => 'CSV data validation failed',
                    'details' => [
                        'errors' => $errors
                    ]
                ], 422);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'INVALID_CSV_FORMAT',
                'message' => 'Failed to parse CSV: ' . $e->getMessage()
            ], 422);
        }

        return $next($request);
    }
}
