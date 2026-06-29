<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientFee;
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

        $recentVisitQuery = PatientVisit::query()
            ->with('patient')
            ->latest();

        $recentPrescriptionQuery = PatientPrescription::query()
            ->with('patient')
            ->latest();

        $recentFeeQuery = PatientFee::query()
            ->with('patient')
            ->latest();

        if ($user->role !== 'admin') {
            $recentVisitQuery->where('doctor_id', $user->id);
            $recentPrescriptionQuery->where('doctor_id', $user->id);
            $recentFeeQuery->where('doctor_id', $user->id);
        }

        $recentActivity = collect()
            ->merge(
                $recentVisitQuery->limit(5)->get()->map(fn ($visit) => [
                    'type' => 'visit',
                    'title' => 'Visit created',
                    'description' => ($visit->patient?->name ?? 'Patient').' - '.($visit->chief_complaint ?: 'No complaint added'),
                    'created_at' => $visit->created_at?->toISOString(),
                ])
            )
            ->merge(
                $recentPrescriptionQuery->limit(5)->get()->map(fn ($prescription) => [
                    'type' => 'prescription',
                    'title' => 'Prescription saved',
                    'description' => ($prescription->patient?->name ?? 'Patient').' - '.$prescription->remedy_name.' '.$prescription->potency,
                    'created_at' => $prescription->created_at?->toISOString(),
                ])
            )
            ->merge(
                $recentFeeQuery->limit(5)->get()->map(fn ($fee) => [
                    'type' => 'fee',
                    'title' => 'Fee recorded',
                    'description' => ($fee->patient?->name ?? 'Patient').' - '.$fee->currency.' '.$fee->total_amount.' / '.$fee->payment_status,
                    'created_at' => $fee->created_at?->toISOString(),
                ])
            )
            ->sortByDesc('created_at')
            ->take(8)
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
