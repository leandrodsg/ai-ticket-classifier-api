<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Rate limits by endpoint:
     * - POST /api/v1/tickets/upload: 25 requests per hour per IP
     * - POST /api/v1/csv/generate: 10 requests per minute per IP
     * - All other endpoints: 60 requests per minute per IP
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $ip = $request->ip();
        $method = $request->method();
        $path = $request->path();

        // Determine rate limit based on endpoint
        $limits = $this->getRateLimits($method, $path);

        // Check if request should be rate limited
        if ($this->isRateLimited($ip, $limits)) {
            return $this->rateLimitExceededResponse($limits);
        }

        // Add rate limit headers to response
        $response = $next($request);

        if ($response instanceof SymfonyResponse) {
            $this->addRateLimitHeaders($response, $ip, $limits);
        }

        return $response;
    }

    /**
     * Get rate limits for the given method and path.
     */
    private function getRateLimits(string $method, string $path): array
    {
        // Normalize path (remove leading slash)
        $normalizedPath = ltrim($path, '/');

        // POST /api/v1/tickets/upload or POST /test/upload: 25 per hour
        if ($method === 'POST' && ($normalizedPath === 'api/v1/tickets/upload' || $normalizedPath === 'test/upload')) {
            return [
                'max_attempts' => 25,
                'decay_seconds' => 3600, // 1 hour
                'key_suffix' => 'upload',
            ];
        }

        // POST /api/v1/csv/generate or POST /test/generate: 10 per minute
        if ($method === 'POST' && ($normalizedPath === 'api/v1/csv/generate' || $normalizedPath === 'test/generate')) {
            return [
                'max_attempts' => 10,
                'decay_seconds' => 60, // 1 minute
                'key_suffix' => 'generate',
            ];
        }

        // All other endpoints: 60 per minute
        return [
            'max_attempts' => 60,
            'decay_seconds' => 60, // 1 minute
            'key_suffix' => 'default',
        ];
    }

    /**
     * Check if the request should be rate limited.
     */
    private function isRateLimited(string $ip, array $limits): bool
    {
        $key = $this->getCacheKey($ip, $limits['key_suffix']);

        // Get current data (count and reset time)
        $data = Cache::get($key, ['count' => 0, 'reset_at' => 0]);
        $attempts = $data['count'] ?? 0;
        $resetAt = $data['reset_at'] ?? 0;

        // Check if window has expired, reset if needed
        $now = time();
        if ($now > $resetAt) {
            $attempts = 0;
            $resetAt = $now + $limits['decay_seconds'];
        }

        // Check if limit exceeded
        if ($attempts >= $limits['max_attempts']) {
            return true;
        }

        // Increment attempts counter and store with reset time
        Cache::put($key, [
            'count' => $attempts + 1,
            'reset_at' => $resetAt
        ], $limits['decay_seconds']);

        return false;
    }

    /**
     * Generate cache key for rate limiting.
     */
    private function getCacheKey(string $ip, string $suffix): string
    {
        return "rate_limit:{$suffix}:{$ip}";
    }

    /**
     * Return rate limit exceeded response.
     */
    private function rateLimitExceededResponse(array $limits): SymfonyResponse
    {
        return response()->json([
            'error' => 'rate_limit_exceeded',
            'message' => 'Too many requests. Please try again later.',
        ], 429);
    }

    /**
     * Add rate limit headers to the response.
     */
    private function addRateLimitHeaders(SymfonyResponse $response, string $ip, array $limits): void
    {
        $key = $this->getCacheKey($ip, $limits['key_suffix']);
        $data = Cache::get($key, ['count' => 0, 'reset_at' => 0]);
        $attempts = $data['count'] ?? 0;
        $resetAt = $data['reset_at'] ?? 0;

        // If no reset time set, calculate it
        if ($resetAt === 0) {
            $resetAt = time() + $limits['decay_seconds'];
        }

        $remaining = max(0, $limits['max_attempts'] - $attempts);

        $response->headers->set('X-RateLimit-Limit', $limits['max_attempts']);
        $response->headers->set('X-RateLimit-Remaining', $remaining);
        $response->headers->set('X-RateLimit-Reset', $resetAt);
    }
}
