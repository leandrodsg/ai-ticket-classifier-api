<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Services\Ai\AllModelsFailedException;
use App\Services\Tickets\TicketUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TicketUploadController extends Controller
{
    public function __construct(
        private TicketUploadService $uploadService
    ) {}

    /**
     * Upload and classify tickets from CSV
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'csv_content' => 'required|string',
                'filename' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'validation_error',
                    'message' => 'Validation failed',
                    'details' => $validator->errors(),
                ], 422);
            }

            // Decode base64 CSV content
            $csvContent = base64_decode($request->input('csv_content'), true);
            if ($csvContent === false) {
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_base64',
                    'message' => 'Invalid base64 encoding for csv_content',
                ], 400);
            }

            // Check file size (5MB limit)
            if (strlen($csvContent) > 5 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'error' => 'payload_too_large',
                    'message' => 'CSV file size exceeds maximum limit of 5MB',
                    'details' => [
                        'file_size' => strlen($csvContent),
                        'max_size' => 5 * 1024 * 1024,
                    ],
                ], 413);
            }

            // Process the upload
            $result = $this->uploadService->process($csvContent);

            return response()->json($result, 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'validation_error',
                'message' => $e->getMessage(),
                'details' => $e->getErrors(),
            ], 422);
        } catch (AllModelsFailedException $e) {
            Log::error('AI service error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'ai_service_error',
                'message' => 'AI classification failed - all models unavailable',
            ], 503);
        } catch (\PDOException | \Illuminate\Database\QueryException $e) {
            Log::critical('Database error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'database_error',
                'message' => 'Database error occurred',
            ], 500);
        } catch (\InvalidArgumentException $e) {
            $errorCode = $this->mapExceptionToErrorCode($e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $errorCode,
                'message' => $e->getMessage(),
            ], $this->getHttpStatusForError($errorCode));
        } catch (\Exception $e) {
            Log::critical('Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'internal_error',
                'message' => 'An unexpected error occurred',
            ], 500);
        }
    }

    private function mapExceptionToErrorCode(string $message): string
    {
        return match (strtolower($message)) {
            'invalid csv signature' => 'invalid_signature',
            'csv has expired' => 'csv_expired',
            'nonce has already been used' => 'replay_attack',
            default => 'validation_error',
        };
    }

    private function getHttpStatusForError(string $errorCode): int
    {
        return match ($errorCode) {
            'invalid_signature', 'csv_expired', 'replay_attack' => 400,
            'validation_error' => 422,
            default => 500,
        };
    }
}
