<?php

namespace App\Services\Cache;

class SemanticCacheKeyGenerator
{
    private const CACHE_KEY_PREFIX = 'classification';

    /**
     * Generate semantic cache key for ticket classification.
     * Uses normalized text to improve cache hit rate for similar tickets.
     */
    public function generateKey(array $ticket): string
    {
        $normalizedText = $this->normalizeText(
            ($ticket['summary'] ?? '') . ' ' . ($ticket['description'] ?? '')
        );

        $hash = hash('sha256', $normalizedText);

        return self::CACHE_KEY_PREFIX . ':' . $hash;
    }

    /**
     * Normalize text for semantic similarity.
     * - Convert to lowercase
     * - Remove punctuation
     * - Remove timestamps and numbers
     * - Remove common stopwords
     * - Remove extra whitespace
     */
    public function normalizeText(string $text): string
    {
        // Convert to lowercase
        $normalized = strtolower($text);

        // Remove punctuation (keep spaces and alphanumeric)
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);

        // Remove timestamps (patterns like YYYY-MM-DD, HH:MM, etc.)
        $normalized = preg_replace('/\d{4}-\d{2}-\d{2}/', '', $normalized); // dates
        $normalized = preg_replace('/\d{2}:\d{2}(:\d{2})?/', '', $normalized); // times
        $normalized = preg_replace('/\d+/', '', $normalized); // remaining numbers

        // Remove common stopwords (but keep important negation words)
        $stopwords = [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            'i', 'you', 'he', 'she', 'it', 'we', 'they', 'this', 'that', 'these', 'those',
            'is', 'am', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had',
            'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must',
            'can', 'wont', 'dont', 'doesnt', 'didnt', 'isnt', 'arent', 'wasnt'
        ];

        $words = explode(' ', $normalized);
        $filteredWords = array_filter($words, function($word) use ($stopwords) {
            return !in_array($word, $stopwords) && strlen($word) > 0;
        });

        // Remove extra whitespace
        $normalized = implode(' ', $filteredWords);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = trim($normalized);

        return $normalized;
    }
}