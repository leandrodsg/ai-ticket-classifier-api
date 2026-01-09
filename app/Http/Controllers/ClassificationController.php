<?php

namespace App\Http\Controllers;

use App\Models\ClassificationJob;
use App\Models\Ticket;
use App\Services\Ai\AiClassificationService;
use App\Services\Csv\CsvParser;
use App\Services\Csv\CsvSanitizer;
use App\Services\Itil\ItilPriorityCalculator;
use App\Services\Itil\SlaCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ClassificationController extends Controller
{
    public function __construct(
        private AiClassificationService $aiService,
        private CsvParser $csvParser,
        private CsvSanitizer $csvSanitizer,
        private ItilPriorityCalculator $priorityCalculator,
        private SlaCalculator $slaCalculator
    ) {}

    public function classify(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $validator = Validator::make($request->all(), [
                'csv_content' => 'required|string|max:5242880', // 5MB in bytes
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()->all(),
                ], 422);
            }

            $csvContent = $request->input('csv_content');

            $parsed = $this->csvParser->parse($csvContent);

            if (empty($parsed['data_rows'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'No valid tickets found in CSV',
                ], 422);
            }

            if (count($parsed['data_rows']) > 20) {
                return response()->json([
                    'success' => false,
                    'error' => 'Maximum 20 tickets allowed per request',
                ], 422);
            }

            $sessionId = Str::uuid()->toString();
            $totalTickets = count($parsed['data_rows']);

            DB::beginTransaction();

            try {
                $job = ClassificationJob::create([
                    'session_id' => $sessionId,
                    'status' => 'processing',
                    'total_tickets' => $totalTickets,
                    'processed_tickets' => 0,
                ]);

                $processedTickets = [];

                foreach ($parsed['data_rows'] as $index => $row) {
                    try {
                        $ticketData = $this->extractTicketData($row);

                        $classification = $this->aiService->classify($ticketData);

                        $priority = $this->priorityCalculator->calculatePriority(
                            $classification['impact'],
                            $classification['urgency']
                        );

                        $slaDueDate = $this->slaCalculator->calculateDueDate(
                            $priority,
                            Carbon::now()
                        );

                        $ticket = Ticket::create([
                            'job_id' => $job->id,
                            'issue_key' => $ticketData['issue_key'],
                            'summary' => $ticketData['summary'],
                            'description' => $ticketData['description'],
                            'reporter' => $ticketData['reporter'],
                            'category' => $classification['category'],
                            'sentiment' => $classification['sentiment'],
                            'priority' => $priority,
                            'impact' => $classification['impact'],
                            'urgency' => $classification['urgency'],
                            'sla_due_date' => $slaDueDate,
                            'reasoning' => $classification['reasoning'],
                        ]);

                        $processedTickets[] = [
                            'issue_key' => $ticket->issue_key,
                            'summary' => $ticket->summary,
                            'description' => $ticket->description,
                            'reporter' => $ticket->reporter,
                            'classification' => [
                                'category' => $ticket->category,
                                'sentiment' => $ticket->sentiment,
                                'priority' => $ticket->priority,
                                'impact' => $ticket->impact,
                                'urgency' => $ticket->urgency,
                                'sla_due_date' => $ticket->sla_due_date->toIso8601String(),
                                'reasoning' => $ticket->reasoning,
                            ],
                        ];

                        $job->update(['processed_tickets' => $index + 1]);

                    } catch (\InvalidArgumentException $e) {
                        // Validation errors should return 422, not 500
                        Log::warning('Validation error processing ticket', [
                            'job_id' => $job->id,
                            'ticket_index' => $index,
                            'error' => $e->getMessage(),
                        ]);

                        DB::rollBack();

                        return response()->json([
                            'success' => false,
                            'error' => 'Validation error',
                            'message' => 'Invalid ticket data: ' . $e->getMessage(),
                            'ticket_index' => $index,
                        ], 422);
                    } catch (\Exception $e) {
                        Log::error('Failed to process ticket', [
                            'job_id' => $job->id,
                            'ticket_index' => $index,
                            'error' => $e->getMessage(),
                        ]);

                        DB::rollBack();

                        throw $e;
                    }
                }

                $processingTimeMs = (int) ((microtime(true) - $startTime) * 1000);

                $job->update([
                    'status' => 'completed',
                    'results' => $processedTickets,
                    'processing_time_ms' => $processingTimeMs,
                    'completed_at' => Carbon::now(),
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'session_id' => $sessionId,
                    'metadata' => [
                        'total_tickets' => $totalTickets,
                        'processed_tickets' => count($processedTickets),
                        'processing_time_ms' => $processingTimeMs,
                    ],
                    'tickets' => $processedTickets,
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();

                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Classification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Classification failed',
                'message' => app()->environment('production')
                    ? 'An error occurred during classification'
                    : $e->getMessage(),
            ], 500);
        }
    }

    private function extractTicketData(array $row): array
    {
        $issueKey = $row['issue_key'] ?? $row['Issue Key'] ?? null;
        $summary = $row['summary'] ?? $row['Summary'] ?? null;
        $description = $row['description'] ?? $row['Description'] ?? null;
        $reporter = $row['reporter'] ?? $row['Reporter'] ?? null;

        if (!$issueKey || !$summary || !$description || !$reporter) {
            throw new \InvalidArgumentException('Missing required fields: issue_key, summary, description, reporter');
        }

        if (strlen($issueKey) > 20) {
            throw new \InvalidArgumentException('Issue key must be 20 characters or less');
        }

        if (strlen($summary) < 5 || strlen($summary) > 200) {
            throw new \InvalidArgumentException('Summary must be between 5 and 200 characters');
        }

        if (strlen($description) < 10 || strlen($description) > 2000) {
            throw new \InvalidArgumentException('Description must be between 10 and 2000 characters');
        }

        if (!filter_var($reporter, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Reporter must be a valid email address');
        }

        return [
            'issue_key' => $issueKey,
            'summary' => $summary,
            'description' => $description,
            'reporter' => $reporter,
        ];
    }
}
