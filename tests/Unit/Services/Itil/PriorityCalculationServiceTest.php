<?php

namespace Tests\Unit\Services\Itil;

use App\Services\Itil\ItilPriorityCalculator;
use App\Services\Itil\PriorityCalculationService;
use App\Services\Itil\SlaCalculator;
use Carbon\Carbon;
use Tests\TestCase;

class PriorityCalculationServiceTest extends TestCase
{
    private PriorityCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $priorityCalculator = new ItilPriorityCalculator();
        $slaCalculator = new SlaCalculator();

        $this->service = new PriorityCalculationService(
            $priorityCalculator,
            $slaCalculator
        );
    }

    public function test_it_calculates_complete_priority_and_sla_information()
    {
        $createdAt = Carbon::parse('2025-12-10T10:00:00Z');

        $result = $this->service->calculatePriorityAndSla('High', 'High', $createdAt);

        $expected = [
            'priority' => 'Critical',
            'impact' => 'High',
            'urgency' => 'High',
            'sla_due_date' => '2025-12-10T11:00:00+00:00',
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_it_calculates_priority_only()
    {
        $priority = $this->service->calculatePriority('High', 'Medium');

        $this->assertEquals('High', $priority);
    }

    public function test_it_calculates_sla_due_date_only()
    {
        $createdAt = Carbon::parse('2025-12-10T10:00:00Z');
        $expectedDueDate = Carbon::parse('2025-12-10T14:00:00Z');

        $dueDate = $this->service->calculateSlaDueDate('High', $createdAt);

        $this->assertEquals($expectedDueDate, $dueDate);
    }

    public function test_it_handles_case_insensitive_input_in_complete_calculation()
    {
        $createdAt = Carbon::parse('2025-12-10T10:00:00Z');

        $result = $this->service->calculatePriorityAndSla('HIGH', 'medium', $createdAt);

        $expected = [
            'priority' => 'High',
            'impact' => 'High',
            'urgency' => 'Medium',
            'sla_due_date' => '2025-12-10T14:00:00+00:00',
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_it_returns_correct_data_types()
    {
        $createdAt = Carbon::parse('2025-12-10T10:00:00Z');

        $result = $this->service->calculatePriorityAndSla('Medium', 'Low', $createdAt);

        $this->assertIsString($result['priority']);
        $this->assertIsString($result['impact']);
        $this->assertIsString($result['urgency']);
        $this->assertIsString($result['sla_due_date']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $result['sla_due_date']);
    }

    public function test_it_calculates_different_priority_levels_correctly()
    {
        $createdAt = Carbon::parse('2025-12-10T10:00:00Z');

        // Critical: High impact + High urgency
        $critical = $this->service->calculatePriorityAndSla('High', 'High', $createdAt);
        $this->assertEquals('Critical', $critical['priority']);
        $this->assertEquals('2025-12-10T11:00:00+00:00', $critical['sla_due_date']);

        // High: High impact + Medium urgency
        $high = $this->service->calculatePriorityAndSla('High', 'Medium', $createdAt);
        $this->assertEquals('High', $high['priority']);
        $this->assertEquals('2025-12-10T14:00:00+00:00', $high['sla_due_date']);

        // Medium: Low impact + High urgency
        $medium = $this->service->calculatePriorityAndSla('Low', 'High', $createdAt);
        $this->assertEquals('Medium', $medium['priority']);
        $this->assertEquals('2025-12-12T10:00:00+00:00', $medium['sla_due_date']);

        // Low: Low impact + Low urgency
        $low = $this->service->calculatePriorityAndSla('Low', 'Low', $createdAt);
        $this->assertEquals('Low', $low['priority']);
        $this->assertEquals('2025-12-17T10:00:00+00:00', $low['sla_due_date']);
    }
}
