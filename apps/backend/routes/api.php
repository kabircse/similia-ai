<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PatientVisitController;
use App\Http\Controllers\Api\PatientVisitAiController;
use App\Http\Controllers\Api\CaseRubricController;
use App\Http\Controllers\Api\RepertoryRubricController;

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
    Route::apiResource('patients', PatientController::class);
    Route::apiResource('patients.visits', PatientVisitController::class);
    Route::post(
        '/patients/{patient}/visits/{visit}/structure-case',
        [PatientVisitAiController::class, 'structure']
    );
    Route::get('/repertory/rubrics', [RepertoryRubricController::class, 'index']);
    Route::get('/repertory/rubrics/{rubric}', [RepertoryRubricController::class, 'show']);

    Route::get('/patients/{patient}/visits/{visit}/rubrics', [CaseRubricController::class, 'index']);
    Route::post('/patients/{patient}/visits/{visit}/rubrics', [CaseRubricController::class, 'store']);
    Route::patch('/patients/{patient}/visits/{visit}/rubrics/{caseRubric}', [CaseRubricController::class, 'update']);
    Route::delete('/patients/{patient}/visits/{visit}/rubrics/{caseRubric}', [CaseRubricController::class, 'destroy']);

    Route::get('/dashboard', function () {
        return response()->json([
            'message' => 'Welcome to Similia AI Doctor Dashboard',
        ]);
    });
});