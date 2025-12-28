<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RateLimitMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RateLimitMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private RateLimitMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RateLimitMiddleware();
    }



    public function test_counter_increments_per_ip(): void
    {
        $request = Request::create('/api/v1/health', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        // First request should pass
        $response = $this->middleware->handle($request, function () {
            return response()->json(['status' => 'ok']);
        });

        $this->assertEquals(200, $response->getStatusCode());

        // Check that counter was incremented
        $key = 'rate_limit:default:192.168.1.1';
        $data = Cache::get($key);
        $this->assertEquals(1, $data['count']);
    }

    public function test_reset_after_time_window(): void
    {
        $request = Request::create('/api/v1/health', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        // Make requests up to limit
        for ($i = 0; $i < 60; $i++) {
            $response = $this->middleware->handle($request, function () {
                return response()->json(['status' => 'ok']);
            });
            $this->assertEquals(200, $response->getStatusCode());
        }

        // Next request should be rate limited
        $response = $this->middleware->handle($request, function () {
            return response()->json(['status' => 'ok']);
        });
        $this->assertEquals(429, $response->getStatusCode());

        // Simulate time passing (manually clear the rate limit key)
        Cache::forget('rate_limit:default:192.168.1.1');

        // Request should work again
        $response = $this->middleware->handle($request, function () {
            return response()->json(['status' => 'ok']);
        });
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_headers_are_correct(): void
    {
        $request = Request::create('/api/v1/health', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['status' => 'ok']);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('60', $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals('59', $response->headers->get('X-RateLimit-Remaining'));
        $this->assertIsNumeric($response->headers->get('X-RateLimit-Reset'));
    }

    public function test_429_response_when_exceeded(): void
    {
        $request = Request::create('/api/v1/health', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        // Exceed the limit
        for ($i = 0; $i < 61; $i++) {
            $response = $this->middleware->handle($request, function () {
                return response()->json(['status' => 'ok']);
            });
        }

        // Last response should be 429
        $this->assertEquals(429, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('rate_limit_exceeded', $data['error']);
        $this->assertEquals('Too many requests. Please try again later.', $data['message']);
    }

    public function test_upload_endpoint_has_stricter_limits(): void
    {
        $request = Request::create('/api/v1/tickets/upload', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.2');

        // Make requests up to upload limit
        for ($i = 0; $i < 25; $i++) {
            $response = $this->middleware->handle($request, function () {
                return response()->json(['status' => 'ok']);
            });
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('25', $response->headers->get('X-RateLimit-Limit'));
        }

        // Next request should be rate limited
        $response = $this->middleware->handle($request, function () {
            return response()->json(['status' => 'ok']);
        });
        $this->assertEquals(429, $response->getStatusCode());
    }

    public function test_generate_endpoint_has_different_limits(): void
    {
        $request = Request::create('/api/csv/generate', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.3');

        // Make requests up to generate limit
        for ($i = 0; $i < 10; $i++) {
            $response = $this->middleware->handle($request, function () {
                return response()->json(['status' => 'ok']);
            });
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('10', $response->headers->get('X-RateLimit-Limit'));
        }

        // Next request should be rate limited
        $response = $this->middleware->handle($request, function () {
            return response()->json(['status' => 'ok']);
        });
        $this->assertEquals(429, $response->getStatusCode());
    }

    public function test_different_ips_have_separate_counters(): void
    {
        $request1 = Request::create('/api/v1/health', 'GET');
        $request1->server->set('REMOTE_ADDR', '192.168.1.10');

        $request2 = Request::create('/api/v1/health', 'GET');
        $request2->server->set('REMOTE_ADDR', '192.168.1.11');

        // Both should work (separate counters)
        $response1 = $this->middleware->handle($request1, function () {
            return response()->json(['status' => 'ok']);
        });
        $response2 = $this->middleware->handle($request2, function () {
            return response()->json(['status' => 'ok']);
        });

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());
    }

    public function test_remaining_decreases_correctly(): void
    {
        $request = Request::create('/api/v1/health', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.12');

        // First request
        $response1 = $this->middleware->handle($request, function () {
            return response()->json(['status' => 'ok']);
        });
        $this->assertEquals('59', $response1->headers->get('X-RateLimit-Remaining'));

        // Second request
        $response2 = $this->middleware->handle($request, function () {
            return response()->json(['status' => 'ok']);
        });
        $this->assertEquals('58', $response2->headers->get('X-RateLimit-Remaining'));
    }
}
