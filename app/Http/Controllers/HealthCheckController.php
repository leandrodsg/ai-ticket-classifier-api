<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HealthCheckController extends Controller
{
    public function check(): JsonResponse
    {
        $startTime = microtime(true);
        $checks = [];
        $overallHealthy = true;

        $checks['database'] = $this->checkDatabase();
        $checks['cache'] = $this->checkCache();
        $checks['ai_service'] = $this->checkAiService();
        $checks['disk_space'] = $this->checkDiskSpace();

        foreach ($checks as $check) {
            // Only 'unhealthy' status causes overall failure
            // 'degraded' is acceptable (partially functional)
            if ($check['status'] === 'unhealthy') {
                $overallHealthy = false;
                break;
            }
        }

        $responseTime = (int) ((microtime(true) - $startTime) * 1000);

        $response = [
            'status' => $overallHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'service' => 'AI Ticket Classifier API',
            'version' => '1.0.0',
            'response_time_ms' => $responseTime,
            'checks' => $checks,
        ];

        $statusCode = $overallHealthy ? 200 : 503;

        return response()->json($response, $statusCode);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::connection()->select('SELECT 1');
            
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            Log::error('Health check: Database failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'error' => app()->environment('production') ? 'Connection error' : $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'ok';

            Cache::put($testKey, $testValue, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            if ($retrieved === $testValue) {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache is working',
                    'driver' => config('cache.default'),
                ];
            }

            return [
                'status' => 'unhealthy',
                'message' => 'Cache read/write failed',
            ];
        } catch (\Exception $e) {
            Log::error('Health check: Cache failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unhealthy',
                'message' => 'Cache connection failed',
                'error' => app()->environment('production') ? 'Connection error' : $e->getMessage(),
            ];
        }
    }

    private function checkAiService(): array
    {
        try {
            $apiKey = config('services.openrouter.api_key') ?: env('OPENROUTER_API_KEY');

            if (!$apiKey) {
                return [
                    'status' => 'degraded',
                    'message' => 'OpenRouter API key not configured',
                ];
            }

            $httpResponse = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->get('https://openrouter.ai/api/v1/models');

            if ($httpResponse->ok()) {
                return [
                    'status' => 'healthy',
                    'message' => 'OpenRouter API accessible',
                ];
            }

            return [
                'status' => 'unhealthy',
                'message' => 'OpenRouter API returned error',
                'status_code' => $httpResponse->status(),
            ];
        } catch (\Exception $e) {
            Log::warning('Health check: AI service check failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'degraded',
                'message' => 'OpenRouter API unreachable',
                'error' => app()->environment('production') ? 'Connection timeout' : $e->getMessage(),
            ];
        }
    }

    private function checkDiskSpace(): array
    {
        try {
            $storagePath = storage_path();
            $freeSpace = disk_free_space($storagePath);
            $totalSpace = disk_total_space($storagePath);

            $freeSpaceMB = round($freeSpace / 1024 / 1024, 2);
            $totalSpaceMB = round($totalSpace / 1024 / 1024, 2);
            $percentUsed = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);

            $status = 'healthy';
            $message = 'Sufficient disk space available';

            if ($percentUsed > 90) {
                $status = 'unhealthy';
                $message = 'Critical: Disk space critically low';
            } elseif ($percentUsed > 80) {
                $status = 'degraded';
                $message = 'Warning: Disk space running low';
            }

            return [
                'status' => $status,
                'message' => $message,
                'free_space_mb' => $freeSpaceMB,
                'total_space_mb' => $totalSpaceMB,
                'percent_used' => $percentUsed,
            ];
        } catch (\Exception $e) {
            Log::error('Health check: Disk space check failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unknown',
                'message' => 'Unable to check disk space',
                'error' => app()->environment('production') ? 'Check failed' : $e->getMessage(),
            ];
        }
    }
}
