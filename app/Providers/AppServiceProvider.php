<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register PromptBuilderInterface based on config
        $this->app->singleton(\App\Services\Ai\PromptBuilderInterface::class, function ($app) {
            $promptVersion = config('ai.prompt.version', 'original');
            $guard = $app->make(\App\Services\Security\PromptInjectionGuard::class);

            return match ($promptVersion) {
                'optimized' => new \App\Services\Ai\OptimizedPrompt($guard),
                default => new \App\Services\Ai\ClassificationPrompt($guard),
            };
        });

        $this->app->singleton(\App\Services\Ai\OpenRouterClient::class, function ($app) {
            $apiKey = config('services.openrouter.api_key') ?: env('OPENROUTER_API_KEY', '');
            return new \App\Services\Ai\OpenRouterClient($apiKey);
        });

        $this->app->singleton(\App\Services\Ai\AiClassificationService::class, function ($app) {
            $client = $app->make(\App\Services\Ai\OpenRouterClient::class);
            $discoveryService = $app->make(\App\Services\Ai\ModelDiscoveryService::class);
            $promptBuilder = $app->make(\App\Services\Ai\PromptBuilderInterface::class);

            return new \App\Services\Ai\AiClassificationService($client, $promptBuilder, $discoveryService);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
