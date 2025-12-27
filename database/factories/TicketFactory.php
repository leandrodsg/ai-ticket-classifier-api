<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Ticket;
use App\Models\ClassificationJob;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        $categories = ['Technical', 'Commercial', 'Billing', 'General', 'Support'];
        $sentiments = ['Positive', 'Negative', 'Neutral'];
        $priorities = ['Critical', 'High', 'Medium', 'Low'];
        $impacts = ['High', 'Medium', 'Low'];
        $urgencies = ['High', 'Medium', 'Low'];

        $category = $this->faker->randomElement($categories);
        $templateKey = strtolower($category);
        
        $ticketTemplates = $this->getTicketTemplates();
        $template = $ticketTemplates[$templateKey];

        $createdAt = $this->faker->dateTimeBetween('-7 days', 'now');
        $slaDueDate = match($this->faker->randomElement($priorities)) {
            'Critical' => (clone $createdAt)->modify('+1 hour'),
            'High' => (clone $createdAt)->modify('+4 hours'),
            'Medium' => (clone $createdAt)->modify('+2 days'),
            'Low' => (clone $createdAt)->modify('+7 days'),
        };

        return [
            'job_id' => ClassificationJob::factory(),
            'issue_key' => $this->generateIssueKey(),
            'summary' => $template['summary'],
            'description' => $template['description'],
            'reporter' => $this->generateRealisticEmail(),
            'category' => $category,
            'sentiment' => $this->faker->randomElement($sentiments),
            'priority' => $this->faker->randomElement($priorities),
            'impact' => $this->faker->randomElement($impacts),
            'urgency' => $this->faker->randomElement($urgencies),
            'sla_due_date' => $slaDueDate,
            'reasoning' => $this->generateReasoning(),
            'created_at' => $createdAt,
        ];
    }

    public function technical(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Technical',
            'summary' => $this->generateTechnicalSummary(),
            'description' => $this->generateTechnicalDescription(),
        ]);
    }

    public function commercial(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Commercial',
            'summary' => $this->generateCommercialSummary(),
            'description' => $this->generateCommercialDescription(),
        ]);
    }

    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Billing',
            'summary' => $this->generateBillingSummary(),
            'description' => $this->generateBillingDescription(),
        ]);
    }

    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Support',
            'summary' => $this->generateSupportSummary(),
            'description' => $this->generateSupportDescription(),
        ]);
    }

    public function general(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'General',
            'summary' => $this->generateGeneralSummary(),
            'description' => $this->generateGeneralDescription(),
        ]);
    }

    private function generateIssueKey(): string
    {
        $project = $this->faker->randomElement(['DEMO', 'API', 'SYS', 'WEB', 'DB']);
        $number = $this->faker->numberBetween(1, 9999);
        
        return sprintf('%s-%04d', $project, $number);
    }

    private function generateRealisticEmail(): string
    {
        $domains = ['example.com', 'company.org', 'business.net', 'client.co', 'user.io'];
        $names = ['john.doe', 'jane.smith', 'mike.jones', 'sarah.wilson', 'david.brown'];
        
        return sprintf('%s@%s', $this->faker->randomElement($names), $this->faker->randomElement($domains));
    }

    private function getTicketTemplates(): array
    {
        return [
            'technical' => [
                'summary' => $this->generateTechnicalSummary(),
                'description' => $this->generateTechnicalDescription()
            ],
            'commercial' => [
                'summary' => $this->generateCommercialSummary(),
                'description' => $this->generateCommercialDescription()
            ],
            'billing' => [
                'summary' => $this->generateBillingSummary(),
                'description' => $this->generateBillingDescription()
            ],
            'support' => [
                'summary' => $this->generateSupportSummary(),
                'description' => $this->generateSupportDescription()
            ],
            'general' => [
                'summary' => $this->generateGeneralSummary(),
                'description' => $this->generateGeneralDescription()
            ]
        ];
    }

    private function generateTechnicalSummary(): string
    {
        return $this->faker->randomElement([
            'Cannot access user dashboard after login',
            'API endpoint returning 500 error on POST requests',
            'Database connection timeout during peak hours',
            'File upload failing for files larger than 5MB',
            'Authentication token expires prematurely',
            'Search functionality not returning results',
            'Payment gateway integration throwing exceptions',
            'Mobile app crashes on iOS devices',
            'Email notifications not being sent',
            'Session data not persisting across requests'
        ]);
    }

    private function generateTechnicalDescription(): string
    {
        $descriptions = [
            'User reports being unable to access their dashboard after successful login. The page loads but shows a blank screen. Error logs indicate a JavaScript exception. Browser: Chrome 120. OS: Windows 11.',
            'When attempting to create a new record via the API POST endpoint, the server returns a 500 Internal Server Error. The request payload is valid JSON. Reproduction steps: Send POST to /api/v1/records with required fields.',
            'During peak usage hours (2-4 PM), the application experiences frequent database connection timeouts. Error: "SQLSTATE[HY000] [2002] Connection timed out". Current connection pool size: 20.',
            'File uploads for documents larger than 5MB consistently fail with a timeout error. Smaller files upload successfully. Error occurs at exactly the 5MB threshold.',
            'User authentication tokens are expiring after only 15 minutes instead of the configured 8 hours. This is happening for all users regardless of activity level.',
            'The search functionality on the main dashboard returns no results even for common terms that should match existing records. The search index appears to be empty.',
            'The payment gateway integration is throwing PHP exceptions when processing credit card transactions. Error: "Gateway timeout while processing payment request".',
            'The mobile application crashes immediately upon launch on iOS devices running version 17.1. The app works fine on Android and older iOS versions.',
            'System-generated email notifications are not being delivered to users. SMTP configuration appears correct. No errors in mail log.',
            'User sessions are not persisting across page requests. Users are logged out unexpectedly. Session configuration: database driver, 8-hour timeout.'
        ];
        
        return $this->faker->randomElement($descriptions);
    }

    private function generateCommercialSummary(): string
    {
        return $this->faker->randomElement([
            'Request for bulk discount on enterprise license',
            'Inquiry about API rate limits for high-volume usage',
            'Feature request: Advanced analytics dashboard',
            'Question about data export capabilities',
            'Request for custom integration support',
            'Inquiry about white-label options',
            'Feature request: Multi-language support',
            'Question about data residency requirements',
            'Request for dedicated account manager',
            'Inquiry about compliance certifications'
        ]);
    }

    private function generateCommercialDescription(): string
    {
        $descriptions = [
            'Our organization is interested in purchasing 500 enterprise licenses and would like to discuss bulk pricing options. Current annual budget allocated for this purpose.',
            'We are planning to implement high-volume API usage (approximately 100,000 requests per day) and need information about rate limits and potential enterprise-tier pricing.',
            'We would like to request the development of an advanced analytics dashboard with custom reporting capabilities, data visualization, and export options.',
            'Can you provide details about the data export functionality? We need to export customer data in various formats (CSV, JSON, XML) for compliance purposes.',
            'Our development team needs assistance with integrating our existing CRM system with your platform. Custom integration support would be beneficial.',
            'We are interested in white-labeling options for our partner network. What are the available customization options and associated costs?',
            'We have international customers and need multi-language support in the application interface. Which languages are currently supported?',
            'Due to data privacy regulations in our region, we need information about data residency and where our data will be stored.',
            'We would like to request a dedicated account manager for our enterprise account to ensure optimal service and support.',
            'We require compliance with SOC 2, ISO 27001, and GDPR. What certifications does your platform currently hold?'
        ];
        
        return $this->faker->randomElement($descriptions);
    }

    private function generateBillingSummary(): string
    {
        return $this->faker->randomElement([
            'Invoice discrepancy: charged twice for same service',
            'Question about proration when upgrading plan mid-cycle',
            'Payment method keeps getting declined',
            'Request for detailed billing breakdown',
            'Overcharged for feature that was supposed to be included',
            'Question about tax calculation on invoices',
            'Need to update billing information',
            'Request for annual billing discount',
            'Payment processing error for subscription renewal',
            'Question about usage-based billing calculation'
        ]);
    }

    private function generateBillingDescription(): string
    {
        $descriptions = [
            'I notice that I was charged twice for the same service in this month\'s invoice. Both charges show the same date and amount. Please investigate and provide a refund.',
            'I upgraded my plan from Basic to Professional in the middle of my billing cycle. How is the proration calculated, and will I receive a credit for unused time?',
            'My credit card keeps getting declined when trying to process the monthly subscription payment. The card is valid and has sufficient funds. Please assist.',
            'I need a detailed breakdown of all charges on my most recent invoice. The summary is too general and I need line-item details for accounting purposes.',
            'I was charged for a premium feature that should be included in my current plan. According to the plan comparison, this feature should be free.',
            'The tax calculation on my invoice seems incorrect. I am located in California and the tax rate applied doesn\'t match the current state tax rate.',
            'I need to update my billing information including the credit card on file and the billing address for my business account.',
            'I am interested in switching to annual billing for my subscription. What discount is available for annual payments versus monthly?',
            'My subscription renewal payment failed to process automatically. I need to manually update the payment method and process the renewal.',
            'I have questions about how the usage-based billing is calculated for the API calls. The usage report doesn\'t match my internal tracking.'
        ];
        
        return $this->faker->randomElement($descriptions);
    }

    private function generateSupportSummary(): string
    {
        return $this->faker->randomElement([
            'How to reset user password in admin panel',
            'Question about integrating with Slack notifications',
            'Need help configuring email templates',
            'How to export user data for GDPR compliance',
            'Question about backup and restore procedures',
            'Need assistance with setting up user roles and permissions',
            'How to customize the application branding',
            'Question about system requirements and compatibility',
            'Need help with data migration from old system',
            'How to enable two-factor authentication'
        ]);
    }

    private function generateSupportDescription(): string
    {
        $descriptions = [
            'I need step-by-step instructions on how to reset a user password through the admin panel. The user is locked out and needs access restored.',
            'We would like to integrate Slack notifications for important system events. What is the process to configure webhooks and notification settings?',
            'I need help customizing the email templates for password resets and account notifications. The default templates need to match our brand guidelines.',
            'We need to export all user data for GDPR compliance purposes. What is the recommended process to export complete user records including preferences?',
            'Can you provide detailed information about the backup and restore procedures? We need to understand the backup frequency and recovery process.',
            'We need assistance with setting up proper user roles and permissions for our team. The current setup is too permissive and needs restriction.',
            'We want to customize the application branding including logo, colors, and company name. What are the available customization options?',
            'We are planning to upgrade our server infrastructure. What are the minimum system requirements and compatibility requirements for optimal performance?',
            'We are migrating from our legacy system and need assistance with the data migration process. What tools and procedures are available?',
            'We want to enable two-factor authentication for all user accounts. What is the process to enable and enforce 2FA across the platform?'
        ];
        
        return $this->faker->randomElement($descriptions);
    }

    private function generateGeneralSummary(): string
    {
        return $this->faker->randomElement([
            'General inquiry about platform capabilities',
            'Question about getting started with the service',
            'Request for platform demo or trial access',
            'Inquiry about partnership opportunities',
            'Question about available integrations',
            'Request for technical documentation',
            'Inquiry about customer support availability',
            'Question about security and data protection',
            'Request for case studies or testimonials',
            'General feedback about user experience'
        ]);
    }

    private function generateGeneralDescription(): string
    {
        $descriptions = [
            'I would like to learn more about the platform capabilities and how it can benefit our organization. Can you provide a comprehensive overview?',
            'We are interested in getting started with your service but need guidance on the initial setup process and best practices.',
            'We would like to request a demo of the platform or trial access to evaluate the features and functionality before making a decision.',
            'We are interested in exploring partnership opportunities with your company. What partnership programs are available?',
            'We need information about available integrations with third-party tools and services that our organization already uses.',
            'We need access to comprehensive technical documentation to evaluate the platform architecture and integration possibilities.',
            'What are the customer support availability hours and what support channels are available for technical assistance?',
            'We have questions about the security measures and data protection protocols implemented by the platform.',
            'We would like to review case studies and customer testimonials to understand how other organizations have successfully implemented the solution.',
            'We have general feedback about the user experience and would like to share suggestions for platform improvements.'
        ];
        
        return $this->faker->randomElement($descriptions);
    }

    private function generateReasoning(): string
    {
        $reasonings = [
            'This ticket involves technical system functionality and requires immediate attention due to user impact.',
            'The issue affects multiple users and has significant business impact, requiring urgent resolution.',
            'This is a commercial inquiry that requires sales team involvement for proper handling and follow-up.',
            'The billing issue requires immediate resolution to maintain customer satisfaction and prevent account suspension.',
            'This support request involves user guidance and can be resolved through documentation or training.',
            'The technical issue requires code-level investigation and may indicate a system-wide problem.',
            'This commercial inquiry has high revenue potential and should be prioritized for sales engagement.',
            'The billing discrepancy requires investigation and potential refund processing, affecting customer trust.',
            'This general inquiry requires comprehensive information sharing to help the prospect understand platform value.',
            'The support request involves configuration assistance and can be resolved through guided setup.'
        ];
        
        return $this->faker->randomElement($reasonings);
    }
}
