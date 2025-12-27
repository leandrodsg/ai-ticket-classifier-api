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
        $this->app->singleton(\App\Services\Ai\OpenRouterClient::class, function ($app) {
            $apiKey = config('services.openrouter.api_key') ?: env('OPENROUTER_API_KEY', '');
            return new \App\Services\Ai\OpenRouterClient($apiKey);
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
