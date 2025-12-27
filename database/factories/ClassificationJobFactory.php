<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\ClassificationJob;

class ClassificationJobFactory extends Factory
{
    protected $model = ClassificationJob::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']);
        $totalTickets = $this->faker->numberBetween(1, 50);
        $processedTickets = match($status) {
            'pending' => 0,
            'processing' => $this->faker->numberBetween(1, $totalTickets - 1),
            'completed' => $totalTickets,
            'failed' => $this->faker->numberBetween(0, $totalTickets),
        };

        $createdAt = $this->faker->dateTimeBetween('-1 hour', 'now');
        $completedAt = $status === 'completed' 
            ? (clone $createdAt)->modify("+{$this->faker->numberBetween(1, 30)} seconds")
            : null;

        return [
            'session_id' => (string) Str::uuid(),
            'status' => $status,
            'total_tickets' => $totalTickets,
            'processed_tickets' => $processedTickets,
            'results' => $this->generateResults($status, $processedTickets),
            'processing_time_ms' => $status === 'completed' 
                ? $this->faker->numberBetween(500, 30000)
                : null,
            'created_at' => $createdAt,
            'completed_at' => $completedAt,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'processed_tickets' => 0,
            'processing_time_ms' => null,
            'completed_at' => null,
            'results' => [],
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'processed_tickets' => $this->faker->numberBetween(1, $attributes['total_tickets'] - 1),
            'processing_time_ms' => null,
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $totalTickets = $attributes['total_tickets'];
            $processingTime = $this->faker->numberBetween(500, 30000);
            $createdAt = $attributes['created_at'];
            
            return [
                'status' => 'completed',
                'processed_tickets' => $totalTickets,
                'processing_time_ms' => $processingTime,
                'completed_at' => (clone $createdAt)->modify("+" . ($processingTime / 1000) . " seconds"),
                'results' => $this->generateResults('completed', $totalTickets),
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'processing_time_ms' => $this->faker->numberBetween(100, 5000),
            'results' => $this->generateResults('failed', 0),
        ]);
    }

    private function generateResults(string $status, int $processedTickets): array
    {
        if ($status === 'pending' || $status === 'processing') {
            return [];
        }

        if ($status === 'failed') {
            return [
                'error' => $this->faker->randomElement([
                    'AI service unavailable',
                    'CSV validation failed',
                    'Processing timeout',
                    'Invalid signature'
                ]),
                'failed_at' => now()->toIso8601String()
            ];
        }

        $results = [];
        for ($i = 0; $i < $processedTickets; $i++) {
            $results[] = [
                'issue_key' => sprintf('DEMO-%03d', $i + 1),
                'category' => $this->faker->randomElement(['Technical', 'Commercial', 'Billing', 'General', 'Support']),
                'sentiment' => $this->faker->randomElement(['Positive', 'Negative', 'Neutral']),
                'priority' => $this->faker->randomElement(['Critical', 'High', 'Medium', 'Low']),
                'impact' => $this->faker->randomElement(['High', 'Medium', 'Low']),
                'urgency' => $this->faker->randomElement(['High', 'Medium', 'Low']),
                'sla_due_date' => $this->faker->dateTimeBetween('now', '+7 days')->format('c'),
                'reasoning' => $this->faker->sentences(2, true),
                'model_used' => $this->faker->randomElement([
                    'google/gemini-2.0-flash-exp:free',
                    'qwen/qwen3-coder:free',
                    'meta-llama/llama-3.2-3b-instruct:free'
                ])
            ];
        }

        return $results;
    }
}
