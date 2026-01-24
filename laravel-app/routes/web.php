<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SmartBulbController;

Route::get('/', [SmartBulbController::class, 'dashboard']);

// API Routes
Route::prefix('api')->group(function () {
    Route::post('/bulb/control', [SmartBulbController::class, 'bulbControl']);
    Route::post('/effect/control', [SmartBulbController::class, 'effectControl']);
    Route::post('/group/control', [SmartBulbController::class, 'groupControl']);
    Route::get('/status', [SmartBulbController::class, 'getStatus']);
    Route::post('/command', [SmartBulbController::class, 'sendCommand']);
});