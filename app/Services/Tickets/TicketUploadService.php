<?php

namespace App\Services\Tickets;

use App\Exceptions\ValidationException;
use App\Models\ClassificationJob;
use App\Models\Ticket;
use App\Services\Ai\AiClassificationService;
use App\Services\Ai\ConcurrentAiClassifier;
use App\Services\Cache\ClassificationCacheRepository;
use App\Services\Csv\CsvParser;
use App\Services\Csv\CsvSanitizer;
use App\Services\Csv\CsvValidator;
use App\Services\Itil\PriorityCalculationService;
use App\Services\Security\HmacSignatureService;
use App\Services\Security\NonceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TicketUploadService
{
    public function __construct(
        private CsvParser $csvParser,
        private CsvSanitizer $csvSanitizer,
        private CsvValidator $csvValidator,
        private HmacSignatureService $hmacService,
        private NonceService $nonceService,
        private AiClassificationService $aiService,
        private ConcurrentAiClassifier $concurrentAiService,
        private PriorityCalculationService $priorityService,
        private ClassificationCacheRepository $cacheRepository
    ) {}

    public function process(string $csvContent): array
    {
        $startTime = microtime(true);

        try {
            DB::beginTransaction();

            // 1. Parse CSV and extract metadata
            $lines = $this->splitLines($csvContent);
            $metadata = $this->csvParser->extractMetadata($lines);
            $dataRows = $this->csvParser->extractDataRows($lines);

            // 2. Validate HMAC signature
            $this->validateSignature($metadata);

            // 3. Validate not expired
            $this->validateExpiration($metadata);

            // 4. Check nonce (prevent replay)
            $this->validateNonce($metadata);

            // 5. Validate CSV schema
            $this->csvValidator->validateSchema($dataRows);

            // 6. Sanitize data
            $sanitizedRows = $this->sanitizeDataRows($dataRows);

            // 7. Create classification job
            $job = $this->createClassificationJob($metadata, count($sanitizedRows));

            // 8. Process each ticket
            $processedTickets = $this->processTickets($sanitizedRows, $job);

            // 9. Update job with results
            $this->finalizeJob($job, $processedTickets, $startTime);

            DB::commit();

            return [
                'success' => true,
                'session_id' => $job->session_id,
                'metadata' => [
                    'total_tickets' => count($processedTickets),
                    'processed_tickets' => count($processedTickets),
                    'processing_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                ],
                'cache_metrics' => $this->cacheRepository->getMetrics(),
                'tickets' => $processedTickets,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            // Capture error context BEFORE rollback for debugging
            $errorContext = [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'session_id' => $metadata['session_id'] ?? 'unknown',
                'processed_tickets' => isset($job) ? $job->processed_tickets : 0,
                'total_tickets' => isset($job) ? $job->total_tickets : 0,
            ];

            Log::error('Ticket upload processing failed', $errorContext);
            
            // Try to save error state to job (if exists) for troubleshooting
            if (isset($job)) {
                try {
                    // Use a separate transaction to update job status
                    DB::transaction(function () use ($job, $e) {
                        $job->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                            'completed_at' => now(),
                        ]);
                    });
                } catch (\Exception $updateError) {
                    // If update fails, at least we have the log above
                    Log::warning('Failed to update job error status', [
                        'job_id' => $job->id,
                        'update_error' => $updateError->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }

    private function validateSignature(array $metadata): void
    {
        // Build data to sign with guaranteed field order (same as generator)
        $requiredFields = ['version', 'timestamp', 'session_id', 'row_count', 'nonce'];
        $dataToSign = [];
        
        // Validate all required fields are present BEFORE validating signature
        foreach ($requiredFields as $field) {
            if (!isset($metadata[$field]) || $metadata[$field] === '') {
                throw new \InvalidArgumentException("Missing required metadata field: {$field}");
            }
            $dataToSign[$field] = $metadata[$field];
        }
        
        // Validate signature is present
        if (!isset($metadata['signature']) || $metadata['signature'] === '') {
            throw new \InvalidArgumentException('Missing signature in CSV metadata');
        }

        // CRITICAL: Use exact same field order as generator
        $dataToSign = [
            'version' => $metadata['version'],
            'timestamp' => $metadata['timestamp'],
            'session_id' => $metadata['session_id'],
            'row_count' => $metadata['row_count'],
            'nonce' => $metadata['nonce'],
        ];

        if (!$this->hmacService->validate($dataToSign, $metadata['signature'])) {
            Log::warning('HMAC validation failed', [
                'expected_fields' => array_keys($dataToSign),
                'received_metadata' => array_keys($metadata),
            ]);
            throw new \InvalidArgumentException('Invalid CSV signature');
        }
    }

    private function validateExpiration(array $metadata): void
    {
        $expiresAt = Carbon::parse($metadata['expires_at']);

        if (now()->isAfter($expiresAt)) {
            throw new \InvalidArgumentException('CSV has expired');
        }
    }

    private function validateNonce(array $metadata): void
    {
        if (!$this->nonceService->validate($metadata['nonce'])) {
            throw new \InvalidArgumentException('Nonce has already been used');
        }
    }

    private function sanitizeDataRows(array $dataRows): array
    {
        return array_map(function ($row) {
            return array_map(function ($value) {
                return $this->csvSanitizer->sanitize($value);
            }, $row);
        }, $dataRows);
    }

    private function createClassificationJob(array $metadata, int $ticketCount): ClassificationJob
    {
        return ClassificationJob::create([
            'id' => Str::uuid(),
            'session_id' => $metadata['session_id'],
            'status' => 'processing',
            'total_tickets' => $ticketCount,
            'processed_tickets' => 0,
            'results' => null,
            'processing_time_ms' => 0,
            'created_at' => now(),
            'completed_at' => null,
        ]);
    }

    private function processTickets(array $sanitizedRows, ClassificationJob $job): array
    {
        $processedTickets = [];
        $ticketsToCreate = [];
        $timestamp = now();

        // Phase 1: Prepare ticket data and check for duplicates
        foreach ($sanitizedRows as $row) {
            // SECURITY: Check if issue_key already exists
            $existingTicket = Ticket::where('issue_key', $row['issue_key'])->first();

            if ($existingTicket) {
                Log::warning('Duplicate issue_key attempted', [
                    'issue_key' => $row['issue_key'],
                    'job_id' => $job->id,
                ]);

                throw new ValidationException(
                    'One or more tickets contain duplicate issue keys',
                    ['error' => 'duplicate_ticket']
                );
            }

            $ticketsToCreate[] = [
                'job_id' => $job->id,
                'issue_key' => $row['issue_key'],
                'summary' => $row['summary'],
                'description' => $row['description'],
                'reporter' => $row['reporter'],
                'category' => null,
                'sentiment' => null,
                'priority' => null,
                'impact' => null,
                'urgency' => null,
                'sla_due_date' => null,
                'reasoning' => null,
                'created_at' => isset($row['created']) ? Carbon::parse($row['created']) : $timestamp,
            ];
        }

        // Phase 2: Create tickets (allows automatic updated_at)
        foreach ($ticketsToCreate as $ticketData) {
            Ticket::create($ticketData);
        }

        // Phase 3: Retrieve created tickets and classify them using concurrent processing
        $tickets = Ticket::where('job_id', $job->id)
            ->orderBy('created_at')
            ->get();

        // Separate tickets that need AI classification vs those that can use cache
        $ticketsNeedingAi = [];
        $cachedClassifications = [];

        foreach ($tickets as $ticket) {
            $ticketData = [
                'issue_key' => $ticket->issue_key,
                'summary' => $ticket->summary,
                'description' => $ticket->description,
                'reporter' => $ticket->reporter,
            ];

            // Try to get from cache first
            $cached = $this->cacheRepository->getCached($ticketData);

            if ($cached !== null) {
                // Cache hit - use cached classification
                $cachedClassifications[$ticket->id] = $cached;
            } else {
                // Cache miss - needs AI classification
                $ticketsNeedingAi[] = $ticketData;
            }
        }

        // Phase 4: Classify tickets needing AI using concurrent processing
        $aiClassifications = [];
        if (!empty($ticketsNeedingAi)) {
            Log::info('Processing tickets with concurrent AI classification', [
                'total_tickets' => count($tickets),
                'cached' => count($cachedClassifications),
                'needing_ai' => count($ticketsNeedingAi)
            ]);

            $aiClassifications = $this->concurrentAiService->classifyBatch($ticketsNeedingAi);

            // Store AI results in cache for future use
            foreach ($ticketsNeedingAi as $index => $ticketData) {
                if (isset($aiClassifications[$index])) {
                    $this->cacheRepository->setCached($ticketData, $aiClassifications[$index]);
                }
            }
        }

        // Phase 5: Combine cache and AI results, calculate priorities and prepare updates
        $ticketUpdates = [];
        $aiIndex = 0;

        foreach ($tickets as $ticket) {
            $ticketId = $ticket->id;

            // Get classification (from cache or AI)
            if (isset($cachedClassifications[$ticketId])) {
                $classification = $cachedClassifications[$ticketId];
            } else {
                $classification = $aiClassifications[$aiIndex] ?? $this->createFallbackClassification();
                $aiIndex++;
            }

            // Calculate priority (ITIL) and SLA
            $priorityAndSla = $this->priorityService->calculatePriorityAndSla(
                $classification['impact'],
                $classification['urgency'],
                $ticket->created_at
            );

            // Collect updates for batch processing
            $ticketUpdates[] = [
                'id' => $ticket->id,
                'category' => $classification['category'],
                'sentiment' => $classification['sentiment'],
                'priority' => $priorityAndSla['priority'],
                'impact' => $priorityAndSla['impact'],
                'urgency' => $priorityAndSla['urgency'],
                'sla_due_date' => Carbon::parse($priorityAndSla['sla_due_date']),
                'reasoning' => $classification['reasoning'],
            ];

            $processedTickets[] = [
                'issue_key' => $ticket->issue_key,
                'summary' => $ticket->summary,
                'description' => $ticket->description,
                'reporter' => $ticket->reporter,
                'classification' => [
                    'category' => $classification['category'],
                    'sentiment' => $classification['sentiment'],
                    'priority' => $priorityAndSla['priority'],
                    'impact' => $priorityAndSla['impact'],
                    'urgency' => $priorityAndSla['urgency'],
                    'sla_due_date' => $priorityAndSla['sla_due_date'],
                    'reasoning' => $classification['reasoning'],
                ],
            ];
        }

        // Phase 6: Bulk update all tickets
        $this->bulkUpdateTickets($ticketUpdates);

        Log::info('Ticket processing completed', [
            'total_tickets' => count($tickets),
            'cached_hits' => count($cachedClassifications),
            'ai_classifications' => count($ticketsNeedingAi)
        ]);

        return $processedTickets;
    }

    /**
     * Bulk update tickets with classification results
     */
    private function bulkUpdateTickets(array $updates): void
    {
        foreach ($updates as $data) {
            Ticket::where('id', $data['id'])->update([
                'category' => $data['category'],
                'sentiment' => $data['sentiment'],
                'priority' => $data['priority'],
                'impact' => $data['impact'],
                'urgency' => $data['urgency'],
                'sla_due_date' => $data['sla_due_date'],
                'reasoning' => $data['reasoning'],
            ]);
        }
    }

    private function classifyTicket(Ticket $ticket): array
    {
        $ticketData = [
            'issue_key' => $ticket->issue_key,
            'summary' => $ticket->summary,
            'description' => $ticket->description,
            'reporter' => $ticket->reporter,
        ];

        // Try to get from cache first
        $cached = $this->cacheRepository->getCached($ticketData);
        
        if ($cached !== null) {
            // Cache hit - return cached classification
            return $cached;
        }

        // Cache miss - classify with AI
        $classification = $this->aiService->classify($ticketData);
        
        // Store in cache for future use
        $this->cacheRepository->setCached($ticketData, $classification);

        return $classification;
    }

    private function finalizeJob(ClassificationJob $job, array $processedTickets, float $startTime): void
    {
        $processingTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        $job->update([
            'status' => 'completed',
            'processed_tickets' => count($processedTickets),
            'results' => json_encode($processedTickets),
            'processing_time_ms' => $processingTimeMs,
            'completed_at' => now(),
        ]);
    }

    /**
     * Create fallback classification for tickets that fail processing
     */
    private function createFallbackClassification(): array
    {
        return [
            'category' => 'General',
            'sentiment' => 'Neutral',
            'impact' => 'Medium',
            'urgency' => 'Medium',
            'reasoning' => 'Fallback classification due to processing failure',
            'model_used' => 'fallback'
        ];
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
