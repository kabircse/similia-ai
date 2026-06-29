<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();

        $patientQuery = Patient::query();

        $visitQuery = PatientVisit::query();

        $prescriptionQuery = PatientPrescription::query();

        if ($user->role !== 'admin') {
            $visitQuery->where('doctor_id', $user->id);
        }

        if ($user->role !== 'admin') {
            $patientQuery->where('doctor_id', $user->id);
        }

        if ($user->role !== 'admin') {
            $prescriptionQuery->where('doctor_id', $user->id);
        }

        return response()->json([
            'data' => [
                'doctor' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],

                'summary' => [
                    'total_patients' => $patientQuery->count(),
                    'today_visits' => (clone $visitQuery)->whereDate('visit_date', now()->toDateString())->count(),
                    'pending_followups' => 0,
                    'prescriptions_saved' => $prescriptionQuery->count(),
                ],

                'clinical_workflow' => [
                    [
                        'title' => 'Create Patient',
                        'description' => 'Register a new patient profile.',
                        'status' => 'available',
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
