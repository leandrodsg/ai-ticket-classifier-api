<?php

namespace App\Services\Csv;

class CsvGeneratorService
{
    public const TEMPLATE_COUNT = 10;

    /**
     * Generate CSV content with realistic sample tickets
     */
    public function generate(int $ticketCount): string
    {
        $tickets = $this->generateSampleTickets($ticketCount);
        return $this->buildCsvContent($tickets);
    }

    /**
     * Generate array of realistic sample tickets
     */
    private function generateSampleTickets(int $count): array
    {
        $templates = $this->getTicketTemplates();
        $tickets = [];

        for ($i = 1; $i <= $count; $i++) {
            $template = $templates[($i - 1) % count($templates)];

            $tickets[] = [
                'issue_key' => sprintf('DEMO-%03d', $i),
                'issue_type' => $template['issue_type'],
                'summary' => $template['summary'],
                'description' => $template['description'],
                'reporter' => $template['reporter'],
                'assignee' => $template['assignee'] ?? '',
                'priority' => $template['priority'],
                'status' => $template['status'],
                'created' => now()->toIso8601String(),
                'labels' => $template['labels'],
            ];
        }

        return $tickets;
    }

    /**
     * Get predefined ticket templates with realistic sample content
     */
    private function getTicketTemplates(): array
    {
        return [
            // Technical Support - Login Issues
            [
                'issue_type' => 'Support',
                'summary' => 'Cannot access account after password reset',
                'description' => 'User reports unable to login to account after resetting password. Error message "Invalid credentials" appears. User tried multiple times with correct password. Browser: Chrome 120, OS: Windows 11.',
                'reporter' => 'john.smith@company.com',
                'assignee' => 'support@company.com',
                'priority' => 'High',
                'status' => 'Open',
                'labels' => 'login;access;authentication',
            ],
            // Billing Issue
            [
                'issue_type' => 'Bug',
                'summary' => 'Payment processing fails for amounts over $1000',
                'description' => 'Payment gateway returns error 500 when processing credit cards with amounts over $1000. Affects premium subscriptions. Multiple customers reported same issue in last 2 hours. Transaction IDs: TXN-001, TXN-002.',
                'reporter' => 'mary.jones@billing.com',
                'priority' => 'High',
                'status' => 'Open',
                'labels' => 'payment;billing;gateway',
            ],
            // Feature Request
            [
                'issue_type' => 'Story',
                'summary' => 'Add dark mode to mobile application',
                'description' => 'Multiple users requested dark mode feature for better nighttime usage. Would significantly improve user experience. Similar feature already exists in web version. Estimated effort: 2 sprints.',
                'reporter' => 'carlos.rodriguez@users.com',
                'priority' => 'Medium',
                'status' => 'Open',
                'labels' => 'mobile;ui;enhancement',
            ],
            // General Question
            [
                'issue_type' => 'Task',
                'summary' => 'How to change email notification settings',
                'description' => 'User wants to reduce email notification frequency. Cannot find settings page. User guide does not have clear instructions.',
                'reporter' => 'anna.davis@client.com',
                'priority' => 'Low',
                'status' => 'Open',
                'labels' => 'settings;email;documentation',
            ],
            // Critical System Outage
            [
                'issue_type' => 'Incident',
                'summary' => 'Database connection failure in production',
                'description' => 'All users experiencing 503 errors. Database connection pool exhausted. Started at 14:30 UTC. Estimated impact $10,000/hour. Operations team investigating.',
                'reporter' => 'ops.team@company.com',
                'priority' => 'Critical',
                'status' => 'In Progress',
                'labels' => 'outage;database;critical',
            ],
            // Performance Issue
            [
                'issue_type' => 'Bug',
                'summary' => 'Dashboard page loads very slowly',
                'description' => 'Dashboard page is taking more than 10 seconds to load. Affects user experience. Problem reported by 50+ users in last 24 hours. Possible backend performance issue.',
                'reporter' => 'performance.team@company.com',
                'priority' => 'Medium',
                'status' => 'Open',
                'labels' => 'performance;dashboard;backend',
            ],
            // Security Concern
            [
                'issue_type' => 'Bug',
                'summary' => 'Possible SQL injection vulnerability in contact form',
                'description' => 'Security team identified possible SQL injection vulnerability in contact form. Parameters are not being sanitized properly. Urgent fix needed to prevent exploitation.',
                'reporter' => 'security@company.com',
                'priority' => 'Critical',
                'status' => 'Open',
                'labels' => 'security;sql;vulnerability',
            ],
            // Integration Issue
            [
                'issue_type' => 'Bug',
                'summary' => 'External API integration failing intermittently',
                'description' => 'Third-party API integration is failing with error 502 approximately 15% of the time. Logs show communication timeout. Affects critical data synchronization.',
                'reporter' => 'integration.team@company.com',
                'priority' => 'High',
                'status' => 'In Progress',
                'labels' => 'integration;api;timeout',
            ],
            // Documentation Request
            [
                'issue_type' => 'Task',
                'summary' => 'Update API documentation with new endpoints',
                'description' => 'API documentation needs to be updated to include new endpoints added in version 2.0. Code examples and use cases should be included.',
                'reporter' => 'docs.team@company.com',
                'priority' => 'Low',
                'status' => 'Open',
                'labels' => 'documentation;api;update',
            ],
            // Data Migration Issue
            [
                'issue_type' => 'Bug',
                'summary' => 'Data migration corrupted old user records',
                'description' => 'Migration process run yesterday corrupted user data created before 2020. Contact information and preferences were lost. Impact on approximately 5% of user base.',
                'reporter' => 'data.team@company.com',
                'priority' => 'High',
                'status' => 'Open',
                'labels' => 'migration;data;corruption',
            ],
        ];
    }

    /**
     * Build CSV content from ticket array
     */
    private function buildCsvContent(array $tickets): string
    {
        $lines = [];

        // Add header row
        $lines[] = 'Issue Key,Issue Type,Summary,Description,Reporter,Assignee,Priority,Status,Created,Labels';

        // Add data rows
        foreach ($tickets as $ticket) {
            $row = [
                $this->escapeCsvField($ticket['issue_key']),
                $this->escapeCsvField($ticket['issue_type']),
                $this->escapeCsvField($ticket['summary']),
                $this->escapeCsvField($ticket['description']),
                $this->escapeCsvField($ticket['reporter']),
                $this->escapeCsvField($ticket['assignee']),
                $this->escapeCsvField($ticket['priority']),
                $this->escapeCsvField($ticket['status']),
                $this->escapeCsvField($ticket['created']),
                $this->escapeCsvField($ticket['labels']),
            ];

            $lines[] = implode(',', $row);
        }

        return implode("\n", $lines);
    }

    /**
     * Escape CSV field for safe output
     */
    private function escapeCsvField(string $value): string
    {
        // If value contains comma, quote, or newline, wrap in quotes
        if (preg_match('/[,"\\n\\r]/', $value)) {
            // Escape quotes by doubling them
            $value = str_replace('"', '""', $value);
            return '"' . $value . '"';
        }

        return $value;
    }
}
