<?php

namespace App\Services\Itil;

use Carbon\Carbon;

class PriorityCalculationService
{
    private ItilPriorityCalculator $priorityCalculator;
    private SlaCalculator $slaCalculator;

    public function __construct(
        ItilPriorityCalculator $priorityCalculator,
        SlaCalculator $slaCalculator
    ) {
        $this->priorityCalculator = $priorityCalculator;
        $this->slaCalculator = $slaCalculator;
    }

    /**
     * Calculate complete priority information including SLA
     *
     * @param string $impact High|Medium|Low
     * @param string $urgency High|Medium|Low
     * @param Carbon $createdAt
     * @return array
     */
    public function calculatePriorityAndSla(string $impact, string $urgency, Carbon $createdAt): array
    {
        $priority = $this->priorityCalculator->calculatePriority($impact, $urgency);
        $slaDueDate = $this->slaCalculator->calculateDueDate($priority, $createdAt);

        return [
            'priority' => $priority,
            'impact' => ucfirst(strtolower($impact)),
            'urgency' => ucfirst(strtolower($urgency)),
            'sla_due_date' => $slaDueDate->toIso8601String(),
        ];
    }

    /**
     * Calculate only priority from impact and urgency
     *
     * @param string $impact High|Medium|Low
     * @param string $urgency High|Medium|Low
     * @return string
     */
    public function calculatePriority(string $impact, string $urgency): string
    {
        return $this->priorityCalculator->calculatePriority($impact, $urgency);
    }

    /**
     * Calculate only SLA due date from priority
     *
     * @param string $priority Critical|High|Medium|Low
     * @param Carbon $createdAt
     * @return Carbon
     */
    public function calculateSlaDueDate(string $priority, Carbon $createdAt): Carbon
    {
        return $this->slaCalculator->calculateDueDate($priority, $createdAt);
    }
}
