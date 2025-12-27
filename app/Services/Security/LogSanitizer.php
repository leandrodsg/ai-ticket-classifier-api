<?php

namespace App\Services\Security;

class LogSanitizer
{
    /**
     * Sanitize ticket data for logging (remove PII)
     */
    public static function sanitizeTicket(array $ticket): array
    {
        return [
            'issue_key' => $ticket['issue_key'] ?? 'unknown',
            'summary' => self::truncate($ticket['summary'] ?? '', 100),
            // Don't log full description (may contain PII)
            'description_length' => strlen($ticket['description'] ?? ''),
            'reporter' => self::maskEmail($ticket['reporter'] ?? ''),
        ];
    }

    /**
     * Mask email address for privacy
     * Example: john.doe@example.com -> j***.d**@example.com
     */
    public static function maskEmail(string $email): string
    {
        if (empty($email) || !str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        // Mask local part (keep first char and length indicator)
        if (strlen($local) > 2) {
            $maskedLocal = $local[0] . str_repeat('*', min(strlen($local) - 1, 3));
        } else {
            $maskedLocal = str_repeat('*', strlen($local));
        }

        return $maskedLocal . '@' . $domain;
    }

    /**
     * Truncate text to specified length
     */
    public static function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . '...';
    }

    /**
     * Sanitize classification result for logging
     */
    public static function sanitizeClassification(array $classification): array
    {
        $sanitized = $classification;

        // Truncate reasoning if too long
        if (isset($sanitized['reasoning'])) {
            $sanitized['reasoning'] = self::truncate($sanitized['reasoning'], 200);
        }

        return $sanitized;
    }
}
