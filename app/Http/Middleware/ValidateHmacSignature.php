<?php

namespace App\Http\Middleware;

use App\Services\Security\HmacSignatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateHmacSignature
{
    private HmacSignatureService $hmacService;

    public function __construct(HmacSignatureService $hmacService)
    {
        $this->hmacService = $hmacService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // For now, just pass through - will be implemented when we have CSV processing
        // This middleware will be used when we process CSV uploads

        return $next($request);
    }
}
