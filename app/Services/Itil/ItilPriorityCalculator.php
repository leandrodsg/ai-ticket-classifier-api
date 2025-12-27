<?php

namespace App\Services\Itil;

class ItilPriorityCalculator
{
    /**
     * Calculate priority based on ITIL impact and urgency matrix
     *
     * @param string $impact High|Medium|Low
     * @param string $urgency High|Medium|Low
     * @return string Critical|High|Medium|Low
     * @throws \InvalidArgumentException
     */
    public function calculatePriority(string $impact, string $urgency): string
    {
        $impact = strtolower($impact);
        $urgency = strtolower($urgency);

        $this->validateInput($impact, $urgency);

        return match ($impact) {
            'high' => match ($urgency) {
                'high' => 'Critical',
                'medium' => 'High',
                'low' => 'Medium',
            },
            'medium' => match ($urgency) {
                'high' => 'High',
                'medium' => 'Medium',
                'low' => 'Low',
            },
            'low' => match ($urgency) {
                'high' => 'Medium',
                'medium' => 'Low',
                'low' => 'Low',
            },
        };
    }

    /**
     * Validate input parameters
     *
     * @param string $impact
     * @param string $urgency
     * @throws \InvalidArgumentException
     */
    private function validateInput(string $impact, string $urgency): void
    {
        $validValues = ['high', 'medium', 'low'];

        if (!in_array($impact, $validValues)) {
            throw new \InvalidArgumentException("Invalid impact value: {$impact}. Must be one of: " . implode(', ', $validValues));
        }

        if (!in_array($urgency, $validValues)) {
            throw new \InvalidArgumentException("Invalid urgency value: {$urgency}. Must be one of: " . implode(', ', $validValues));
        }
    }
}
