<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ValidateHmacSignature;
use App\Services\Security\HmacSignatureService;
use App\Services\Security\NonceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ValidateHmacSignatureTest extends TestCase
{
    private HmacSignatureService $hmacService;
    private NonceService $nonceService;
    private ValidateHmacSignature $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure security is NOT bypassed by default in tests
        Config::set('app.bypass_security', false);
        
        $this->hmacService = $this->createMock(HmacSignatureService::class);
        $this->nonceService = $this->createMock(NonceService::class);
        $this->middleware = new ValidateHmacSignature($this->hmacService, $this->nonceService);
    }

    public function test_allows_request_with_valid_hmac_signature()
    {
        $timestamp = time();
        $nonce = 'test-nonce-12345';
        $signature = 'valid-signature-hash';

        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => 'test data'
        ]);
        $request->headers->set('X-HMAC-Signature', $signature);
        $request->headers->set('X-Nonce', $nonce);
        $request->headers->set('X-Timestamp', $timestamp);

        $this->nonceService->expects($this->once())
            ->method('validate')
            ->with($nonce)
            ->willReturn(true);

        $this->hmacService->expects($this->once())
            ->method('validate')
            ->willReturn(true);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['success' => true], json_decode($response->getContent(), true));
    }

    public function test_rejects_request_with_missing_signature_header()
    {
        $timestamp = time();
        $nonce = 'test-nonce-12345';

        $request = Request::create('/api/classify', 'POST');
        // Missing X-HMAC-Signature header
        $request->headers->set('X-Nonce', $nonce);
        $request->headers->set('X-Timestamp', $timestamp);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
        $this->assertStringContainsString('Missing required security headers', $data['message']);
    }

    public function test_rejects_request_with_missing_nonce_header()
    {
        $timestamp = time();
        $signature = 'valid-signature-hash';

        $request = Request::create('/api/classify', 'POST');
        $request->headers->set('X-HMAC-Signature', $signature);
        // Missing X-Nonce header
        $request->headers->set('X-Timestamp', $timestamp);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
        $this->assertStringContainsString('Missing required security headers', $data['message']);
    }

    public function test_rejects_request_with_missing_timestamp_header()
    {
        $nonce = 'test-nonce-12345';
        $signature = 'valid-signature-hash';

        $request = Request::create('/api/classify', 'POST');
        $request->headers->set('X-HMAC-Signature', $signature);
        $request->headers->set('X-Nonce', $nonce);
        // Missing X-Timestamp header

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
        $this->assertStringContainsString('Missing required security headers', $data['message']);
    }

    public function test_rejects_request_with_expired_timestamp()
    {
        $timestamp = time() - 400; // 6 minutes ago (> 5 min limit)
        $nonce = 'test-nonce-12345';
        $signature = 'valid-signature-hash';

        $request = Request::create('/api/classify', 'POST');
        $request->headers->set('X-HMAC-Signature', $signature);
        $request->headers->set('X-Nonce', $nonce);
        $request->headers->set('X-Timestamp', $timestamp);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
        $this->assertStringContainsString('timestamp expired', $data['message']);
    }

    public function test_rejects_request_with_future_timestamp()
    {
        $timestamp = time() + 400; // 6 minutes in future (> 5 min limit)
        $nonce = 'test-nonce-12345';
        $signature = 'valid-signature-hash';

        $request = Request::create('/api/classify', 'POST');
        $request->headers->set('X-HMAC-Signature', $signature);
        $request->headers->set('X-Nonce', $nonce);
        $request->headers->set('X-Timestamp', $timestamp);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
        $this->assertStringContainsString('timestamp expired', $data['message']);
    }

    public function test_accepts_request_within_5_minute_window()
    {
        $timestamp = time() - 250; // 4 minutes ago (< 5 min limit)
        $nonce = 'test-nonce-12345';
        $signature = 'valid-signature-hash';

        $request = Request::create('/api/classify', 'POST');
        $request->headers->set('X-HMAC-Signature', $signature);
        $request->headers->set('X-Nonce', $nonce);
        $request->headers->set('X-Timestamp', $timestamp);

        $this->nonceService->expects($this->once())
            ->method('validate')
            ->with($nonce)
            ->willReturn(true);

        $this->hmacService->expects($this->once())
            ->method('validate')
            ->willReturn(true);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_rejects_request_with_reused_nonce_replay_attack()
    {
        $timestamp = time();
        $nonce = 'already-used-nonce';
        $signature = 'valid-signature-hash';

        $request = Request::create('/api/classify', 'POST');
        $request->headers->set('X-HMAC-Signature', $signature);
        $request->headers->set('X-Nonce', $nonce);
        $request->headers->set('X-Timestamp', $timestamp);

        $this->nonceService->expects($this->once())
            ->method('validate')
            ->with($nonce)
            ->willReturn(false); // Nonce already used

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
        $this->assertStringContainsString('replay attack', $data['message']);
    }

    public function test_rejects_request_with_invalid_hmac_signature()
    {
        $timestamp = time();
        $nonce = 'test-nonce-12345';
        $signature = 'invalid-signature-hash';

        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => 'test data'
        ]);
        $request->headers->set('X-HMAC-Signature', $signature);
        $request->headers->set('X-Nonce', $nonce);
        $request->headers->set('X-Timestamp', $timestamp);

        $this->nonceService->expects($this->once())
            ->method('validate')
            ->with($nonce)
            ->willReturn(true);

        $this->hmacService->expects($this->once())
            ->method('validate')
            ->willReturn(false); // Invalid signature

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']);
        $this->assertStringContainsString('Invalid HMAC signature', $data['message']);
    }

    public function test_bypasses_validation_when_security_bypass_enabled()
    {
        Config::set('app.bypass_security', true);

        $request = Request::create('/api/classify', 'POST');
        // No security headers

        Log::shouldReceive('warning')
            ->once()
            ->with('Security bypass is enabled - this should only be used in development', \Mockery::any());

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['success' => true], json_decode($response->getContent(), true));

        Config::set('app.bypass_security', false);
    }

    public function test_validates_hmac_includes_nonce_and_timestamp_in_data()
    {
        $timestamp = time();
        $nonce = 'test-nonce-12345';
        $signature = 'valid-signature-hash';

        $request = Request::create('/api/classify', 'POST', [
            'csv_content' => 'test data',
            'other_field' => 'value'
        ]);
        $request->headers->set('X-HMAC-Signature', $signature);
        $request->headers->set('X-Nonce', $nonce);
        $request->headers->set('X-Timestamp', $timestamp);

        $this->nonceService->expects($this->once())
            ->method('validate')
            ->with($nonce)
            ->willReturn(true);

        $this->hmacService->expects($this->once())
            ->method('validate')
            ->with(
                $this->callback(function ($data) use ($nonce, $timestamp) {
                    return $data['nonce'] === $nonce 
                        && $data['timestamp'] == $timestamp
                        && $data['csv_content'] === 'test data'
                        && $data['other_field'] === 'value';
                }),
                $signature
            )
            ->willReturn(true);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
