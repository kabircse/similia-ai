<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'similia-api',
    ]);
});

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard/overview', [DashboardController::class, 'overview']);

    Route::get('/dashboard', function () {
        return response()->json([
            'message' => 'Welcome to Similia AI Doctor Dashboard',
        ]);
    });
});