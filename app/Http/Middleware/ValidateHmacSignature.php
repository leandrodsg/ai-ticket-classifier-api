<?php

namespace App\Http\Middleware;

use App\Services\Security\HmacSignatureService;
use App\Services\Security\NonceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateHmacSignature
{
    public function __construct(
        private HmacSignatureService $hmacService,
        private NonceService $nonceService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // SECURITY: Only bypass validation if explicitly enabled via env variable
        // This should NEVER be true in production environments
        if (config('app.bypass_security', false)) {
            Log::warning('Security bypass is enabled - this should only be used in development', [
                'environment' => app()->environment(),
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            
            return $next($request);
        }

        // Get security headers
        $signature = $request->header('X-HMAC-Signature');
        $nonce = $request->header('X-Nonce');
        $timestamp = $request->header('X-Timestamp');

        // Validate required headers
        if (!$signature || !$nonce || !$timestamp) {
            Log::warning('Missing security headers', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'has_signature' => !empty($signature),
                'has_nonce' => !empty($nonce),
                'has_timestamp' => !empty($timestamp),
            ]);

            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Missing required security headers: X-HMAC-Signature, X-Nonce, X-Timestamp',
            ], 401);
        }

        // Validate timestamp (prevent replay attacks with old requests)
        $requestTime = intval($timestamp);
        $currentTime = time();
        $maxAge = 300; // 5 minutes

        if (abs($currentTime - $requestTime) > $maxAge) {
            Log::warning('Request timestamp too old or too far in future', [
                'ip' => $request->ip(),
                'timestamp' => $timestamp,
                'age_seconds' => abs($currentTime - $requestTime),
            ]);

            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Request timestamp expired or invalid',
            ], 401);
        }

        // Validate nonce (prevent replay attacks)
        if (!$this->nonceService->validate($nonce)) {
            Log::warning('Nonce replay attack detected', [
                'ip' => $request->ip(),
                'nonce' => substr($nonce, 0, 8) . '...',
                'path' => $request->path(),
            ]);

            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid or reused nonce - possible replay attack',
            ], 401);
        }

        // Validate HMAC signature
        $data = $request->all();
        $data['nonce'] = $nonce;
        $data['timestamp'] = $timestamp;

        if (!$this->hmacService->validate($data, $signature)) {
            Log::warning('Invalid HMAC signature', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid HMAC signature',
            ], 401);
        }

        // All validations passed
        Log::info('HMAC validation successful', [
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);

        return $next($request);
    }
}
