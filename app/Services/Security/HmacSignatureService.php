<?php

namespace App\Services\Security;

use Illuminate\Support\Str;

class HmacSignatureService
{
    private string $key;
    private string $algorithm = 'sha256';

    public function __construct()
    {
        $this->key = config('app.key');
        if (!$this->key) {
            throw new \RuntimeException('APP_KEY is not configured');
        }
    }

    /**
     * Generate HMAC signature for data
     */
    public function generate(array $data): string
    {
        $dataString = $this->prepareDataString($data);
        return hash_hmac($this->algorithm, $dataString, $this->key);
    }

    /**
     * Validate HMAC signature
     */
    public function validate(array $data, string $signature): bool
    {
        $expectedSignature = $this->generate($data);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Prepare data string for signing
     */
    private function prepareDataString(array $data): string
    {
        // Sort keys to ensure consistent ordering
        ksort($data);

        $parts = [];
        foreach ($data as $key => $value) {
            $parts[] = $key . '=' . (string) $value;
        }

        return implode('|', $parts);
    }

    /**
     * Get algorithm being used
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }
}
