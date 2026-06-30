<?php

use App\Http\Controllers\Api\AiTaskController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaseRubricController;
use App\Http\Controllers\Api\ClinicSettingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\KnowledgeSearchController;
use App\Http\Controllers\Api\MateriaMedicaComparisonController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PatientFeeController;
use App\Http\Controllers\Api\PatientPrescriptionController;
use App\Http\Controllers\Api\PatientTimelineController;
use App\Http\Controllers\Api\PatientVisitAiController;
use App\Http\Controllers\Api\PatientVisitController;
use App\Http\Controllers\Api\RemedyController;
use App\Http\Controllers\Api\RemedySuggestionController;
use App\Http\Controllers\Api\RepertorizationController;
use App\Http\Controllers\Api\RepertoryRubricController;
use App\Http\Controllers\Api\UserNotificationController;
use App\Http\Controllers\Api\VisitPrintController;
use App\Http\Controllers\Api\VoiceTranscriptController;
use Illuminate\Support\Facades\Route;

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
    Route::get('/activity-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:view_activity_logs');

    Route::get('/ai-tasks/{aiTask}', [AiTaskController::class, 'show']);

    Route::get('/notifications', [UserNotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [UserNotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{notification}/read', [UserNotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [UserNotificationController::class, 'markAllAsRead']);
    Route::get('/remedies', [RemedyController::class, 'index']);
    Route::get('/remedies/{remedy}', [RemedyController::class, 'show']);
    Route::get('/knowledge/search', [KnowledgeSearchController::class, 'index']);

    Route::middleware('permission:manage_clinic_settings')->group(function () {
        Route::get('/clinic-settings', [ClinicSettingController::class, 'show']);
        Route::put('/clinic-settings', [ClinicSettingController::class, 'update']);
    });

    Route::apiResource('patients', PatientController::class);
    Route::get('/patients/{patient}/timeline', [PatientTimelineController::class, 'index']);
    Route::apiResource('patients.visits', PatientVisitController::class);
    Route::post(
        '/patients/{patient}/visits/{visit}/structure-case',
        [PatientVisitAiController::class, 'structure']
    );
    Route::post(
        '/patients/{patient}/visits/{visit}/structure-case/async',
        [AiTaskController::class, 'structureCase']
    )->middleware('permission:compare_materia_medica');

    Route::get(
        '/patients/{patient}/visits/{visit}/voice-transcripts',
        [VoiceTranscriptController::class, 'index']
    )->middleware('permission:manage_visits');

    Route::post(
        '/patients/{patient}/visits/{visit}/voice-transcripts',
        [VoiceTranscriptController::class, 'store']
    )->middleware('permission:manage_visits');

    Route::get(
        '/patients/{patient}/visits/{visit}/voice-transcripts/{voiceTranscript}',
        [VoiceTranscriptController::class, 'show']
    )->middleware('permission:manage_visits');

    Route::get('/repertory/rubrics', [RepertoryRubricController::class, 'index']);
    Route::get('/repertory/rubrics/{rubric}', [RepertoryRubricController::class, 'show']);

    Route::get('/patients/{patient}/visits/{visit}/rubrics', [CaseRubricController::class, 'index']);
    Route::post('/patients/{patient}/visits/{visit}/rubrics', [CaseRubricController::class, 'store']);
    Route::patch('/patients/{patient}/visits/{visit}/rubrics/{caseRubric}', [CaseRubricController::class, 'update']);
    Route::delete('/patients/{patient}/visits/{visit}/rubrics/{caseRubric}', [CaseRubricController::class, 'destroy']);
    Route::get(
        '/patients/{patient}/visits/{visit}/repertorization-runs',
        [RepertorizationController::class, 'index']
    );

    Route::get(
        '/patients/{patient}/visits/{visit}/print/case-sheet',
        [VisitPrintController::class, 'caseSheet']
    );

    Route::get(
        '/patients/{patient}/visits/{visit}/print/prescription',
        [VisitPrintController::class, 'prescription']
    );

    Route::get(
        '/patients/{patient}/visits/{visit}/repertorization-runs/{run}',
        [RepertorizationController::class, 'show']
    );

    Route::post(
        '/patients/{patient}/visits/{visit}/repertorize/weighted',
        [RepertorizationController::class, 'runWeighted']
    );

    Route::post(
        '/patients/{patient}/visits/{visit}/repertorize/cross',
        [RepertorizationController::class, 'runCross']
    );

    Route::post(
        '/patients/{patient}/visits/{visit}/repertorize/eliminative',
        [RepertorizationController::class, 'runEliminative']
    );

    Route::post(
        '/patients/{patient}/visits/{visit}/materia-medica/compare',
        [MateriaMedicaComparisonController::class, 'compare']
    );
    Route::post(
        '/patients/{patient}/visits/{visit}/materia-medica/compare/async',
        [AiTaskController::class, 'compareMateriaMedica']
    )->middleware('permission:compare_materia_medica');

    Route::get(
        '/patients/{patient}/visits/{visit}/remedy-suggestions',
        [RemedySuggestionController::class, 'index']
    )->middleware('permission:compare_materia_medica');

    Route::get(
        '/patients/{patient}/visits/{visit}/remedy-suggestions/{suggestionRun}',
        [RemedySuggestionController::class, 'show']
    )->middleware('permission:compare_materia_medica');

    Route::post(
        '/patients/{patient}/visits/{visit}/remedy-suggestions/generate',
        [RemedySuggestionController::class, 'generate']
    )->middleware('permission:compare_materia_medica');

    Route::get(
        '/patients/{patient}/visits/{visit}/prescription',
        [PatientPrescriptionController::class, 'show']
    );

    Route::put(
        '/patients/{patient}/visits/{visit}/prescription',
        [PatientPrescriptionController::class, 'save']
    );

    Route::delete(
        '/patients/{patient}/visits/{visit}/prescription',
        [PatientPrescriptionController::class, 'destroy']
    );

    Route::get(
        '/patients/{patient}/visits/{visit}/fee',
        [PatientFeeController::class, 'show']
    );

    Route::put(
        '/patients/{patient}/visits/{visit}/fee',
        [PatientFeeController::class, 'save']
    );

    Route::delete(
        '/patients/{patient}/visits/{visit}/fee',
        [PatientFeeController::class, 'destroy']
    );

    Route::get('/dashboard', function () {
        return response()->json([
            'message' => 'Welcome to Similia AI Doctor Dashboard',
        ]);
    });
});
