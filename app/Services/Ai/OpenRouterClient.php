<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterClient
{
    private const BASE_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const MAX_RETRIES = 2;
    private const TIMEOUT_SECONDS = 15;
    private const TEST_TIMEOUT_SECONDS = 20; // 20 segundos em ambiente de teste

    public function __construct(
        private string $apiKey
    ) {}

    public function callApi(string $model, array $messages): array
    {
        $maxRetries = app()->environment('testing') ? 0 : self::MAX_RETRIES;
        
        $attempts = 0;
        $lastException = null;

        while ($attempts <= $maxRetries) {
            try {
                $response = $this->makeRequest($model, $messages);

                if (isset($response['choices'][0]['message']['content'])) {
                    return $response;
                }

                throw new \Exception('Invalid OpenRouter API response');

            } catch (\Exception $e) {
                $lastException = $e;
                $attempts++;

                Log::warning('OpenRouter API call failed', [
                    'model' => $model,
                    'attempt' => $attempts,
                    'error' => $e->getMessage(),
                    'will_retry' => $attempts <= $maxRetries
                ]);

                if ($attempts <= $maxRetries) {
                    $backoffSeconds = 2 ** ($attempts - 1);
                    sleep($backoffSeconds);
                }
            }
        }

        throw new \Exception(
            "OpenRouter API call failed after " . ($maxRetries + 1) . " attempts: " .
            $lastException->getMessage()
        );
    }

    private function makeRequest(string $model, array $messages): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => 1000,
            'response_format' => ['type' => 'json_object']
        ];

        // Use timeout maior em ambiente de teste para evitar timeouts prematuros
        $timeout = app()->environment('testing') ? self::TEST_TIMEOUT_SECONDS : self::TIMEOUT_SECONDS;

        $httpClient = Http::timeout($timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url', 'http://localhost'),
                'X-Title' => 'AI Ticket Classifier'
            ]);

        if (app()->environment('testing')) {
            $httpClient = $httpClient->withoutVerifying();
        }

        $response = $httpClient->post(self::BASE_URL, $payload);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $statusCode = $response->getStatusCode();

            if ($statusCode === 429) {
                throw new \Exception('Rate limit exceeded');
            }

            throw new \Exception(
                "OpenRouter API error: {$statusCode} - " .
                ($response->getBody() ?: 'Unknown error')
            );
        }

        $data = json_decode($response->getBody(), true);

        if (!$data) {
            throw new \Exception('Invalid JSON response from OpenRouter API');
        }

        return $data;
    }
}
