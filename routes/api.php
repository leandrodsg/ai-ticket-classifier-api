<?php

use App\Http\Controllers\ClassificationController;
use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint (no authentication required)
Route::get('/health', [HealthCheckController::class, 'check']);

// AI Classification endpoint
Route::post('/classify', [ClassificationController::class, 'classify']);

// API info endpoint
Route::get('/info', function () {
    return response()->json([
        'name' => config('app.name'),
        'version' => '1.0.0',
        'environment' => app()->environment(),
        'features' => [
            'ai_classification' => true,
            'csv_processing' => true,
            'itil_sla' => true,
            'auto_discovery' => config('ai.auto_discovery.enabled'),
        ],
    ]);
});
