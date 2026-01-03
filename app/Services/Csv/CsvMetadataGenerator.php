<?php

namespace App\Services\Csv;

use App\Services\Security\HmacSignatureService;
use Illuminate\Support\Str;

class CsvMetadataGenerator
{
    private HmacSignatureService $hmacService;

    public function __construct(HmacSignatureService $hmacService)
    {
        $this->hmacService = $hmacService;
    }

    /**
     * Add cryptographic metadata to CSV content
     */
    public function addMetadata(string $csvContent): string
    {
        $metadata = $this->generateMetadata($csvContent);

        $metadataSection = $this->buildMetadataSection($metadata);

        return $metadataSection . "\n\n" . $csvContent;
    }

    /**
     * Generate all metadata components
     */
    private function generateMetadata(string $csvContent): array
    {
        $sessionId = (string) Str::uuid();
        $timestamp = now()->toIso8601String();
        $nonce = Str::random(32);
        $rowCount = $this->countDataRows($csvContent);
        $expiresAt = now()->addHour()->toIso8601String();

        // Generate HMAC signature
        $dataToSign = [
            'version' => 'v1',
            'timestamp' => $timestamp,
            'session_id' => $sessionId,
            'row_count' => (string) $rowCount,
            'nonce' => $nonce,
        ];

        $signature = $this->hmacService->generate($dataToSign);

        return [
            'version' => 'v1',
            'signature' => $signature,
            'timestamp' => $timestamp,
            'session_id' => $sessionId,
            'row_count' => $rowCount,
            'nonce' => $nonce,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Count data rows in CSV content (excluding header)
     */
    private function countDataRows(string $csvContent): int
    {
        $lines = explode("\n", trim($csvContent));
        $dataLines = 0;

        foreach ($lines as $index => $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // First non-empty line is header, skip it
            if ($index === 0) {
                continue;
            }

            $dataLines++;
        }

        return $dataLines;
    }

    /**
     * Build metadata section as string
     */
    private function buildMetadataSection(array $metadata): string
    {
        $lines = [
            '# METADATA - DO NOT EDIT THIS SECTION',
            '# version: ' . $metadata['version'],
            '# signature: ' . $metadata['signature'],
            '# timestamp: ' . $metadata['timestamp'],
            '# session_id: ' . $metadata['session_id'],
            '# row_count: ' . $metadata['row_count'],
            '# nonce: ' . $metadata['nonce'],
            '# expires_at: ' . $metadata['expires_at'],
            '# END METADATA',
        ];

        return implode("\n", $lines);
    }

    /**
     * Extract metadata from generated CSV content
     */
    public function extractMetadata(string $csvContent): array
    {
        $lines = explode("\n", $csvContent);
        $metadata = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '# END METADATA') {
                break;
            }

            if (str_starts_with($line, '# ') && !str_starts_with($line, '# METADATA')) {
                [$key, $value] = explode(': ', substr($line, 2), 2);
                $metadata[trim($key)] = trim($value);
            }
        }

        return $metadata;
    }
}
