<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClassificationJob;
use Illuminate\Http\JsonResponse;

class TicketQueryController extends Controller
{
    public function show(string $id): JsonResponse
    {
        $job = ClassificationJob::with('tickets')->find($id);

        if (!$job) {
            return response()->json([
                'error' => 'Job not found',
                'message' => 'No classification job exists with the provided ID'
            ], 404);
        }

        $response = [
            'session_id' => $job->id,
            'status' => $job->status,
            'created_at' => $job->created_at->toIso8601String(),
        ];

        if ($job->status === 'completed') {
            $response['completed_at'] = $job->completed_at?->toIso8601String();
            $response['results'] = $job->results;
            $response['tickets'] = $job->tickets->map(function ($ticket) {
                return [
                    'issue_key' => $ticket->issue_key,
                    'summary' => $ticket->summary,
                    'category' => $ticket->category,
                    'sentiment' => $ticket->sentiment,
                    'urgency' => $ticket->urgency,
                    'impact' => $ticket->impact,
                    'priority' => $ticket->priority,
                    'sla_due_date' => $ticket->sla_due_date?->toIso8601String(),
                ];
            });
        } elseif ($job->status === 'failed') {
            $response['completed_at'] = $job->completed_at?->toIso8601String();
            $response['error'] = $job->results['error'] ?? 'Classification failed';
        }

        return response()->json($response);
    }
}
