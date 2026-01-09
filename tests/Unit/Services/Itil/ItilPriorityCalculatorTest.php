<?php

namespace Tests\Unit\Services\Itil;

use App\Services\Itil\ItilPriorityCalculator;
use Tests\TestCase;

class ItilPriorityCalculatorTest extends TestCase
{
    private ItilPriorityCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ItilPriorityCalculator();
    }

    public function test_it_calculates_critical_priority_for_high_impact_high_urgency()
    {
        $priority = $this->calculator->calculatePriority('High', 'High');

        $this->assertEquals('Critical', $priority);
    }

    public function test_it_calculates_high_priority_for_high_impact_medium_urgency()
    {
        $priority = $this->calculator->calculatePriority('High', 'Medium');

        $this->assertEquals('High', $priority);
    }

    public function test_it_calculates_medium_priority_for_high_impact_low_urgency()
    {
        $priority = $this->calculator->calculatePriority('High', 'Low');

        $this->assertEquals('Medium', $priority);
    }

    public function test_it_calculates_high_priority_for_medium_impact_high_urgency()
    {
        $priority = $this->calculator->calculatePriority('Medium', 'High');

        $this->assertEquals('High', $priority);
    }

    public function test_it_calculates_medium_priority_for_medium_impact_medium_urgency()
    {
        $priority = $this->calculator->calculatePriority('Medium', 'Medium');

        $this->assertEquals('Medium', $priority);
    }

    public function test_it_calculates_low_priority_for_medium_impact_low_urgency()
    {
        $priority = $this->calculator->calculatePriority('Medium', 'Low');

        $this->assertEquals('Low', $priority);
    }

    public function test_it_calculates_medium_priority_for_low_impact_high_urgency()
    {
        $priority = $this->calculator->calculatePriority('Low', 'High');

        $this->assertEquals('Medium', $priority);
    }

    public function test_it_calculates_low_priority_for_low_impact_medium_urgency()
    {
        $priority = $this->calculator->calculatePriority('Low', 'Medium');

        $this->assertEquals('Low', $priority);
    }

    public function test_it_calculates_low_priority_for_low_impact_low_urgency()
    {
        $priority = $this->calculator->calculatePriority('Low', 'Low');

        $this->assertEquals('Low', $priority);
    }

    public function test_it_handles_case_insensitive_input()
    {
        $priority1 = $this->calculator->calculatePriority('HIGH', 'high');
        $priority2 = $this->calculator->calculatePriority('high', 'HIGH');

        $this->assertEquals('Critical', $priority1);
        $this->assertEquals('Critical', $priority2);
    }

    public function test_it_throws_exception_for_invalid_impact()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid impact value: invalid');

        $this->calculator->calculatePriority('invalid', 'High');
    }

    public function test_it_throws_exception_for_invalid_urgency()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid urgency value: invalid');

        $this->calculator->calculatePriority('High', 'invalid');
    }

    public function test_it_throws_exception_for_empty_impact()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid impact value: ');

        $this->calculator->calculatePriority('', 'High');
    }

    public function test_it_throws_exception_for_empty_urgency()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid urgency value: ');

        $this->calculator->calculatePriority('High', '');
    }
}
