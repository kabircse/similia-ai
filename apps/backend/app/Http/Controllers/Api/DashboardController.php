<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
            $patientQuery->where('doctor_id', $user->id);
            $prescriptionQuery->where('doctor_id', $user->id);
        }

        $recentActivityQuery = AuditLog::query()
            ->with(['patient', 'visit'])
            ->latest();

        if ($user->role !== 'admin') {
            $recentActivityQuery->where('user_id', $user->id);
        }

        $recentActivity = $recentActivityQuery
            ->limit(8)
            ->get()
            ->map(fn ($log) => [
                'type' => $log->category,
                'action' => $log->action,
                'title' => $log->title,
                'description' => $log->description,
                'patient_id' => $log->patient_id,
                'patient_name' => $log->patient?->name,
                'patient_visit_id' => $log->patient_visit_id,
                'created_at' => $log->created_at?->toISOString(),
            ])
            ->values();

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
                    'pending_followups' => (clone $visitQuery)
                        ->whereNotNull('next_follow_up_date')
                        ->whereDate('next_follow_up_date', '>=', now()->toDateString())
                        ->count(),
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
                        'status' => 'available',
                    ],
                    [
                        'title' => 'Select Rubrics',
                        'description' => 'Search and select repertory rubrics.',
                        'status' => 'available',
                    ],
                    [
                        'title' => 'Repertorize',
                        'description' => 'Run weighted, cross, or eliminative repertorization.',
                        'status' => 'available',
                    ],
                    [
                        'title' => 'Compare Remedies',
                        'description' => 'Use materia medica RAG for remedy comparison.',
                        'status' => 'available',
                    ],
                    [
                        'title' => 'Save Prescription',
                        'description' => 'Save doctor-approved remedy, potency, and follow-up.',
                        'status' => 'available',
                    ],
                ],

                'recent_activity' => $recentActivity,
            ],
        ]);
    }
}
