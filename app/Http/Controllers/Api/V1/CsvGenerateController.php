<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Cache\ClassificationCacheRepository;
use App\Services\Csv\CsvGeneratorService;
use App\Services\Csv\CsvMetadataGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CsvGenerateController extends Controller
{
    public function __construct(
        private CsvGeneratorService $csvGenerator,
        private CsvMetadataGenerator $metadataGenerator,
        private ClassificationCacheRepository $cache
    ) {}

    /**
     * Generate CSV template with cryptographic metadata
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ticket_count' => 'required|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'validation_error',
                'message' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $ticketCount = $request->input('ticket_count');

        // Generate base CSV content
        $csvContent = $this->csvGenerator->generate($ticketCount);

        // Add cryptographic metadata
        $csvWithMetadata = $this->metadataGenerator->addMetadata($csvContent);

        // Encode to base64 with validation
        $csvBase64 = base64_encode($csvWithMetadata);

        // Validate base64 encoding
        if ($csvBase64 === false) {
            return response()->json([
                'success' => false,
                'error' => 'encoding_error',
                'message' => 'Failed to encode CSV content',
            ], 500);
        }

        // Extract metadata for response
        $metadata = $this->metadataGenerator->extractMetadata($csvWithMetadata);

        return response()->json([
            'success' => true,
            'data' => [
                'csv_content' => $csvBase64,
                'filename' => 'tickets_template.csv',
                'metadata' => [
                    'version' => $metadata['version'],
                    'timestamp' => $metadata['timestamp'],
                    'session_id' => $metadata['session_id'],
                    'row_count' => $metadata['row_count'],
                    'expires_at' => $metadata['expires_at'],
                    'signature' => $metadata['signature'],
                    // Note: nonce is not exposed in response for security
                ],
            ],
        ], 200);
    }


}
