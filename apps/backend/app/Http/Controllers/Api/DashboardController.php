<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'doctor' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],

                'summary' => [
                    'total_patients' => 0,
                    'today_visits' => 0,
                    'pending_followups' => 0,
                    'prescriptions_saved' => 0,
                ],

                'clinical_workflow' => [
                    [
                        'title' => 'Create Patient',
                        'description' => 'Register a new patient profile.',
                        'status' => 'coming_next',
                    ],
                    [
                        'title' => 'Take Case',
                        'description' => 'Add manual or AI-assisted case-taking notes.',
                        'status' => 'coming_next',
                    ],
                    [
                        'title' => 'Select Rubrics',
                        'description' => 'Search and select repertory rubrics.',
                        'status' => 'planned',
                    ],
                    [
                        'title' => 'Repertorize',
                        'description' => 'Run weighted, cross, or eliminative repertorization.',
                        'status' => 'planned',
                    ],
                    [
                        'title' => 'Compare Remedies',
                        'description' => 'Use materia medica RAG for remedy comparison.',
                        'status' => 'planned',
                    ],
                    [
                        'title' => 'Save Prescription',
                        'description' => 'Save doctor-approved remedy, potency, and follow-up.',
                        'status' => 'planned',
                    ],
                ],

                'recent_activity' => [],
            ],
        ]);
    }
}