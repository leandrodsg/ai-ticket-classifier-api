<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Illuminate\Support\Facades\DB;

final class EnvironmentValidationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * Validates required environment variables on application boot.
     *
     * @throws RuntimeException
     */
    public function boot(): void
    {
        // Only validate in production
        if (!app()->environment('production')) {
            return;
        }

        $this->validateRequiredVariables();
        $this->validateSecuritySettings();
        $this->validateDatabaseConnection();
    }

    /**
     * Validate required environment variables exist.
     *
     * @throws RuntimeException
     */
    private function validateRequiredVariables(): void
    {
        $required = [
            'APP_KEY' => 'Application encryption key',
            'CSV_SIGNING_KEY' => 'CSV signing key for HMAC',
            'OPENROUTER_API_KEY' => 'OpenRouter API key for AI classification',
        ];

        foreach ($required as $key => $description) {
            $value = config(match ($key) {
                'APP_KEY' => 'app.key',
                'CSV_SIGNING_KEY' => 'services.csv_signing_key',
                'OPENROUTER_API_KEY' => 'services.openrouter.api_key',
            });

            if (empty($value)) {
                throw new RuntimeException(
                    "Missing required environment variable: {$key} ({$description}). " .
                    "Please set this in your .env file or Railway dashboard."
                );
            }

            // Validate APP_KEY format
            if ($key === 'APP_KEY' && !str_starts_with($value, 'base64:')) {
                throw new RuntimeException(
                    "APP_KEY must start with 'base64:'. Run 'php artisan key:generate' to create a valid key."
                );
            }

            // Validate CSV_SIGNING_KEY length (minimum 32 characters)
            if ($key === 'CSV_SIGNING_KEY' && strlen($value) < 32) {
                throw new RuntimeException(
                    "CSV_SIGNING_KEY must be at least 32 characters long for security. " .
                    "Generate with: php -r \"echo bin2hex(random_bytes(32));\""
                );
            }

            // Validate OPENROUTER_API_KEY format
            if ($key === 'OPENROUTER_API_KEY' && !str_starts_with($value, 'sk-or-v1-')) {
                throw new RuntimeException(
                    "OPENROUTER_API_KEY appears invalid. It should start with 'sk-or-v1-'. " .
                    "Get your key from https://openrouter.ai/keys"
                );
            }
        }
    }

    /**
     * Validate security settings for production.
     *
     * @throws RuntimeException
     */
    private function validateSecuritySettings(): void
    {
        // Ensure APP_DEBUG is false in production
        if (config('app.debug') === true) {
            throw new RuntimeException(
                "APP_DEBUG must be set to 'false' in production. " .
                "Debug mode exposes sensitive information."
            );
        }

        // Ensure APP_ENV is set to production
        if (config('app.env') !== 'production') {
            throw new RuntimeException(
                "APP_ENV must be set to 'production'. Current value: " . config('app.env')
            );
        }

        // Warn if using default keys (though validation above should catch this)
        $defaultKeys = [
            'base64:your_key_here',
            'your_key_here',
            'changeme',
            '00000000000000000000000000000000',
        ];

        $appKey = config('app.key');
        $csvKey = config('services.csv_signing_key');

        foreach ($defaultKeys as $default) {
            if (str_contains($appKey, $default) || str_contains($csvKey, $default)) {
                throw new RuntimeException(
                    "Default or weak encryption keys detected. " .
                    "Generate unique keys before deploying to production."
                );
            }
        }
    }

    /**
     * Validate database connection is working.
     *
     * @throws RuntimeException
     */
    private function validateDatabaseConnection(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Database connection failed: {$e->getMessage()}. " .
                "Verify DATABASE_URL is set correctly and the database is accessible."
            );
        }

        // Verify database driver is PostgreSQL for production
        $driver = config('database.default');
        if ($driver === 'sqlite') {
            throw new RuntimeException(
                "SQLite is not recommended for production. Use PostgreSQL (DATABASE_URL)."
            );
        }
    }
}
