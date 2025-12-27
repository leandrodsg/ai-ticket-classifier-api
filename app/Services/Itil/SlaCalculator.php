<?php

namespace App\Services\Itil;

use Carbon\Carbon;

class SlaCalculator
{
    /**
     * Calculate SLA due date based on priority
     *
     * @param string $priority Critical|High|Medium|Low
     * @param Carbon $createdAt
     * @return Carbon
     * @throws \InvalidArgumentException
     */
    public function calculateDueDate(string $priority, Carbon $createdAt): Carbon
    {
        $originalPriority = $priority;
        $priority = ucfirst(strtolower($priority));

        $this->validatePriority($originalPriority);

        return match ($priority) {
            'Critical' => $createdAt->copy()->addHours(1),
            'High' => $createdAt->copy()->addHours(4),
            'Medium' => $createdAt->copy()->addDays(2),
            'Low' => $createdAt->copy()->addDays(7),
        };
    }

    /**
     * Validate priority parameter
     *
     * @param string $priority
     * @throws \InvalidArgumentException
     */
    private function validatePriority(string $priority): void
    {
        $validValues = ['Critical', 'High', 'Medium', 'Low'];
        $normalizedPriority = ucfirst(strtolower($priority));

        if (!in_array($normalizedPriority, $validValues)) {
            throw new \InvalidArgumentException("Invalid priority value: {$priority}. Must be one of: " . implode(', ', $validValues));
        }
    }
}
