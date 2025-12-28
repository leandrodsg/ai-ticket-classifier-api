<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary routes for testing with custom middleware logic
        Route::get('/test/health', function () {
            $middleware = new \App\Http\Middleware\RateLimitMiddleware();
            return $middleware->handle(
                request(),
                function () {
                    return response()->json(['status' => 'ok']);
                }
            );
        });

        Route::post('/test/upload', function () {
            $middleware = new \App\Http\Middleware\RateLimitMiddleware();
            return $middleware->handle(
                request(),
                function () {
                    return response()->json(['status' => 'uploaded']);
                }
            );
        });

        Route::post('/test/generate', function () {
            $middleware = new \App\Http\Middleware\RateLimitMiddleware();
            return $middleware->handle(
                request(),
                function () {
                    return response()->json(['status' => 'generated']);
                }
            );
        });
    }

    public function test_health_endpoint_rate_limiting(): void
    {
        // Make 60 requests (the limit)
        for ($i = 0; $i < 60; $i++) {
            $response = $this->get('/test/health');
            $response->assertStatus(200);
            $response->assertHeader('X-RateLimit-Limit', '60');
            $response->assertHeader('X-RateLimit-Remaining', (string)(59 - $i));
        }

        // 61st request should be rate limited
        $response = $this->get('/test/health');
        $response->assertStatus(429);
        $response->assertJson([
            'error' => 'rate_limit_exceeded',
            'message' => 'Too many requests. Please try again later.'
        ]);
    }

    public function test_upload_endpoint_has_stricter_limits(): void
    {
        // Make 25 requests (the limit for upload)
        for ($i = 0; $i < 25; $i++) {
            $response = $this->postJson('/test/upload', [
                'data' => 'test'
            ]);
            $response->assertStatus(200);
            $response->assertHeader('X-RateLimit-Limit', '25');
        }

        // 26th request should be rate limited
        $response = $this->postJson('/test/upload', [
            'data' => 'test'
        ]);
        $response->assertStatus(429);
    }

    public function test_generate_endpoint_has_different_limits(): void
    {
        // Make 10 requests (the limit for generate)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/test/generate', [
                'count' => 5
            ]);
            $response->assertStatus(200);
            $response->assertHeader('X-RateLimit-Limit', '10');
        }

        // 11th request should be rate limited
        $response = $this->postJson('/test/generate', [
            'count' => 5
        ]);
        $response->assertStatus(429);
    }

    public function test_rate_limit_headers_are_present(): void
    {
        $response = $this->get('/test/health');

        $response->assertStatus(200);
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
        $response->assertHeader('X-RateLimit-Reset');

        // Verify header values are numeric
        $limit = $response->headers->get('X-RateLimit-Limit');
        $remaining = $response->headers->get('X-RateLimit-Remaining');
        $reset = $response->headers->get('X-RateLimit-Reset');

        $this->assertIsNumeric($limit);
        $this->assertIsNumeric($remaining);
        $this->assertIsNumeric($reset);

        // Reset time should be in the future
        $this->assertGreaterThan(time(), (int)$reset);
    }

    public function test_rate_limit_reset_time_is_reasonable(): void
    {
        $response = $this->get('/test/health');

        $resetTime = (int)$response->headers->get('X-RateLimit-Reset');
        $now = time();

        // Reset time should be in the future (for default endpoint: 60 seconds)
        $this->assertGreaterThan($now, $resetTime);
        $this->assertLessThan($now + 120, $resetTime); // Allow buffer for test execution time
    }

    public function test_different_endpoints_have_different_limits(): void
    {
        // Health endpoint: 60 per minute
        $response = $this->get('/test/health');
        $response->assertHeader('X-RateLimit-Limit', '60');

        // Upload endpoint: 25 per hour
        $response = $this->postJson('/test/upload', ['data' => 'test']);
        $response->assertHeader('X-RateLimit-Limit', '25');

        // Generate endpoint: 10 per minute
        $response = $this->postJson('/test/generate', ['count' => 5]);
        $response->assertHeader('X-RateLimit-Limit', '10');
    }

    public function test_rate_limit_exceeded_response_format(): void
    {
        // Exceed rate limit for health endpoint
        for ($i = 0; $i < 61; $i++) {
            $this->get('/test/health');
        }

        // Last response should be properly formatted
        $response = $this->get('/test/health');
        $response->assertStatus(429);
        $response->assertJsonStructure([
            'error',
            'message'
        ]);

        $data = $response->json();
        $this->assertEquals('rate_limit_exceeded', $data['error']);
        $this->assertIsString($data['message']);
    }
}
