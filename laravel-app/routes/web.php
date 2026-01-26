<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SmartBulbController;

// Serve the CSS file
Route::get('/css/smart-dashboard.css', function () {
    return response(file_get_contents(resource_path('css/smart-dashboard.css')))
        ->header('Content-Type', 'text/css');
});

// Serve the JS file
Route::get('/js/smart-dashboard.js', function () {
    return response(file_get_contents(resource_path('js/smart-dashboard.js')))
        ->header('Content-Type', 'application/javascript');
});

// Dashboard Route
Route::get('/', [SmartBulbController::class, 'dashboard']);

// API Routes
Route::prefix('api')->group(function () {
    Route::post('/bulb/control', [SmartBulbController::class, 'bulbControl']);
    Route::post('/effect/control', [SmartBulbController::class, 'effectControl']);
    Route::post('/group/control', [SmartBulbController::class, 'groupControl']);
    Route::get('/status', [SmartBulbController::class, 'getStatus']);
    Route::post('/command', [SmartBulbController::class, 'sendCommand']);
    Route::post('/voice', [SmartBulbController::class, 'voiceCommand']);
});