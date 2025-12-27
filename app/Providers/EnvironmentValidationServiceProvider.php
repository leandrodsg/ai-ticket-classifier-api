<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

class EnvironmentValidationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Validate critical environment variables
        $requiredEnvVars = [
            'APP_KEY' => 'Application encryption key is not set. Run: php artisan key:generate',
            'OPENROUTER_API_KEY' => 'OpenRouter API key is not configured. Set OPENROUTER_API_KEY in .env file',
        ];

        foreach ($requiredEnvVars as $var => $message) {
            if (empty(config('app.key')) && $var === 'APP_KEY') {
                throw new RuntimeException("CRITICAL: {$message}");
            }
            
            if (empty(env('OPENROUTER_API_KEY')) && $var === 'OPENROUTER_API_KEY') {
                // Only enforce in production or if bypass is disabled
                if (app()->environment('production') || !config('app.bypass_security', false)) {
                    throw new RuntimeException("CRITICAL: {$message}");
                }
            }
        }

        // Validate security bypass is disabled in production
        if (app()->environment('production') && config('app.bypass_security', false)) {
            throw new RuntimeException(
                'CRITICAL SECURITY ERROR: BYPASS_SECURITY cannot be enabled in production environment. ' .
                'This disables all HMAC authentication and makes the API completely unsecured.'
            );
        }
    }

    public function register(): void
    {
        //
    }
}
