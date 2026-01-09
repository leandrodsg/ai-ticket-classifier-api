<?php

namespace Tests\Unit\Services\Itil;

use App\Services\Itil\SlaCalculator;
use Carbon\Carbon;
use Tests\TestCase;

class SlaCalculatorTest extends TestCase
{
    private SlaCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new SlaCalculator();
    }

    public function test_it_calculates_sla_due_date_for_critical_priority()
    {
        $createdAt = Carbon::parse('2025-12-10T10:00:00Z');
        $expectedDueDate = Carbon::parse('2025-12-10T11:00:00Z');

        $dueDate = $this->calculator->calculateDueDate('Critical', $createdAt);

        $this->assertEquals($expectedDueDate, $dueDate);
    }

    public function test_it_calculates_sla_due_date_for_high_priority()
    {
        $createdAt = Carbon::parse('2025-12-10T10:00:00Z');
        $expectedDueDate = Carbon::parse('2025-12-10T14:00:00Z');

        $dueDate = $this->calculator->calculateDueDate('High', $createdAt);

        $this->assertEquals($expectedDueDate, $dueDate);
    }

    public function test_it_calculates_sla_due_date_for_medium_priority()
    {
        $createdAt = Carbon::parse('2025-12-10T10:00:00Z');
        $expectedDueDate = Carbon::parse('2025-12-12T10:00:00Z');

        $dueDate = $this->calculator->calculateDueDate('Medium', $createdAt);

        $this->assertEquals($expectedDueDate, $dueDate);
    }

    public function test_it_calculates_sla_due_date_for_low_priority()
    {
        $createdAt = Carbon::parse('2025-12-10T10:00:00Z');
        $expectedDueDate = Carbon::parse('2025-12-17T10:00:00Z');

        $dueDate = $this->calculator->calculateDueDate('Low', $createdAt);

        $this->assertEquals($expectedDueDate, $dueDate);
    }

    public function test_it_handles_case_insensitive_priority_input()
    {
        $createdAt = Carbon::parse('2025-12-10T10:00:00Z');

        $dueDate1 = $this->calculator->calculateDueDate('CRITICAL', $createdAt);
        $dueDate2 = $this->calculator->calculateDueDate('critical', $createdAt);
        $dueDate3 = $this->calculator->calculateDueDate('Critical', $createdAt);

        $expectedDueDate = Carbon::parse('2025-12-10T11:00:00Z');

        $this->assertEquals($expectedDueDate, $dueDate1);
        $this->assertEquals($expectedDueDate, $dueDate2);
        $this->assertEquals($expectedDueDate, $dueDate3);
    }

    public function test_it_handles_timezones_correctly()
    {
        $createdAt = Carbon::parse('2025-12-10T10:00:00+02:00'); // UTC+2
        $expectedDueDate = Carbon::parse('2025-12-10T11:00:00+02:00'); // Should maintain timezone

        $dueDate = $this->calculator->calculateDueDate('Critical', $createdAt);

        $this->assertEquals($expectedDueDate, $dueDate);
        $this->assertEquals('+02:00', $dueDate->getTimezone()->getName());
    }

    public function test_it_does_not_modify_original_datetime()
    {
        $originalCreatedAt = Carbon::parse('2025-12-10T10:00:00Z');
        $createdAtCopy = $originalCreatedAt->copy();

        $this->calculator->calculateDueDate('Critical', $originalCreatedAt);

        // Original should remain unchanged
        $this->assertEquals($createdAtCopy, $originalCreatedAt);
    }

    public function test_it_throws_exception_for_invalid_priority()
    {
        $createdAt = Carbon::parse('2025-12-10T10:00:00Z');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid priority value: invalid');

        $this->calculator->calculateDueDate('invalid', $createdAt);
    }

    public function test_it_throws_exception_for_empty_priority()
    {
        $createdAt = Carbon::parse('2025-12-10T10:00:00Z');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid priority value: ');

        $this->calculator->calculateDueDate('', $createdAt);
    }

    public function test_it_calculates_correct_sla_for_different_days_of_week()
    {
        // Test with Friday to ensure weekend handling doesn't affect calculations
        $fridayCreatedAt = Carbon::parse('2025-12-13T10:00:00Z'); // Friday
        $expectedDueDate = Carbon::parse('2025-12-15T10:00:00Z'); // Sunday (2 days later)

        $dueDate = $this->calculator->calculateDueDate('Medium', $fridayCreatedAt);

        $this->assertEquals($expectedDueDate, $dueDate);
    }
}
