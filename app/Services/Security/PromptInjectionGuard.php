<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Log;

class PromptInjectionGuard
{
    /**
     * Suspicious patterns that indicate prompt injection attempts
     */
    private const SUSPICIOUS_PATTERNS = [
        '/ignore\s+(previous|all|above|prior)\s+(instructions?|rules?|prompts?)/i',
        '/disregard\s+(previous|all|above|prior)/i',
        '/forget\s+(previous|all|above|everything)/i',
        '/system\s*:\s*/i',
        '/assistant\s*:\s*/i',
        '/\[INST\]/i',
        '/<<SYS>>/i',
        '/<\|im_start\|>/i',
        '/respond\s+(with|only)\s+(json)?/i',
        '/output\s+only/i',
        '/you\s+(must|should|are)\s+now/i',
        '/new\s+instructions?/i',
        '/override\s+instructions?/i',
        '/instead\s+of\s+classifying/i',
        '/ignore.*instructions?/i', // Catch-all for "ignore instructions" variations
    ];

    /**
     * Detect potential prompt injection in text
     */
    public function detectInjection(string $text): bool
    {
        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize text to remove potential injection vectors
     */
    public function sanitize(string $text): string
    {
        // Remove control characters (except common whitespace)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Limit length to prevent token exhaustion attacks
        if (strlen($text) > 10000) {
            $text = substr($text, 0, 10000);
        }

        // Trim
        $text = trim($text);

        return $text;
    }

    /**
     * Validate and sanitize ticket data, logging any suspicious activity
     */
    public function validateTicket(array $ticket): array
    {
        $suspicious = false;
        $sanitized = $ticket;

        // Check and sanitize summary
        if (isset($ticket['summary'])) {
            $sanitized['summary'] = $this->sanitize($ticket['summary']);
            
            if ($this->detectInjection($ticket['summary'])) {
                $suspicious = true;
                Log::warning('Prompt injection attempt detected in summary', [
                    'ticket_key' => $ticket['issue_key'] ?? 'unknown',
                    'ip' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                    'summary_preview' => substr($ticket['summary'], 0, 100),
                ]);
            }
        }

        // Check and sanitize description
        if (isset($ticket['description'])) {
            $sanitized['description'] = $this->sanitize($ticket['description']);
            
            if ($this->detectInjection($ticket['description'])) {
                $suspicious = true;
                Log::warning('Prompt injection attempt detected in description', [
                    'ticket_key' => $ticket['issue_key'] ?? 'unknown',
                    'ip' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                    'description_preview' => substr($ticket['description'], 0, 100),
                ]);
            }
        }

        // If suspicious, add flag but continue processing
        if ($suspicious) {
            $sanitized['_suspicious'] = true;
        }

        return $sanitized;
    }

    /**
     * Validate AI response for injection artifacts
     */
    public function validateAiResponse(array $response): bool
    {
        // Check reasoning field for suspicious content
        if (isset($response['reasoning'])) {
            $reasoning = strtolower($response['reasoning']);
            
            $suspiciousKeywords = [
                'ignore',
                'instruction',
                'system:',
                'assistant:',
                '[inst]',
                'disregard',
                'override',
            ];

            foreach ($suspiciousKeywords as $keyword) {
                if (strpos($reasoning, $keyword) !== false) {
                    Log::critical('AI response contains injection artifacts', [
                        'response' => $response,
                        'suspicious_keyword' => $keyword,
                    ]);
                    return false;
                }
            }
        }

        return true;
    }
}
