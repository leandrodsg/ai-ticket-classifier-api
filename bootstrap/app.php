<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // No rate limiting configured (requires Redis/database cache)
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Custom error handling for production
        $exceptions->render(function (Throwable $e, Request $request) {
            // Don't expose stack traces in production
            if (app()->environment('production') && $request->expectsJson()) {
                return response()->json([
                    'error' => 'Internal Server Error',
                    'message' => 'An error occurred. Please contact support if the problem persists.',
                    'request_id' => $request->id(),
                ], 500);
            }
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Clean up expired nonces hourly
        $schedule->command('nonce:cleanup')->hourly();
        
        // Atualiza cache de modelos AI a cada 6 horas
        $schedule->command('ai:discover-models')->everySixHours();
    })
    ->create();
