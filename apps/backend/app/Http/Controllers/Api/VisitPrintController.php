<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\RepertorizationRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitPrintController extends Controller
{
    public function caseSheet(Request $request, Patient $patient, PatientVisit $visit): JsonResponse
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $visit->load([
            'caseRubrics.rubric',
            'prescription',
            'fee',
        ]);

        $runs = collect(['weighted', 'cross', 'eliminative'])
            ->map(function (string $method) use ($visit) {
                return RepertorizationRun::query()
                    ->with(['results' => fn ($query) => $query->orderBy('rank')])
                    ->where('patient_visit_id', $visit->id)
                    ->where('method', $method)
                    ->latest()
                    ->first();
            })
            ->filter()
            ->values();

        return response()->json([
            'data' => [
                'document_type' => 'doctor_case_sheet',
                'generated_at' => now()->toISOString(),

                'clinic' => $this->clinicInfo(),
                'doctor' => $this->doctorInfo($request),

                'patient' => [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'age_years' => $patient->age_years,
                    'gender' => $patient->gender,
                    'phone' => $patient->phone,
                    'address' => $patient->address,
                    'occupation' => $patient->occupation,
                    'marital_status' => $patient->marital_status,
                    'emergency_contact' => $patient->emergency_contact,
                    'notes' => $patient->notes,
                ],

                'visit' => [
                    'id' => $visit->id,
                    'visit_date' => $visit->visit_date?->toDateString(),
                    'visit_type' => $visit->visit_type,
                    'status' => $visit->status,
                    'case_source' => $visit->case_source,
                    'chief_complaint' => $visit->chief_complaint,
                    'raw_case_text' => $visit->raw_case_text,
                    'case_sections' => $visit->case_sections ?? [],
                    'missing_questions' => $visit->missing_questions ?? [],
                    'red_flags' => $visit->red_flags ?? [],
                    'doctor_notes' => $visit->doctor_notes,
                    'next_follow_up_date' => $visit->next_follow_up_date?->toDateString(),
                ],

                'rubrics' => $visit->caseRubrics
                    ->map(fn ($caseRubric) => [
                        'id' => $caseRubric->id,
                        'rubric_path' => $caseRubric->rubric?->rubric_path,
                        'symptom_type' => $caseRubric->symptom_type,
                        'importance' => $caseRubric->importance,
                        'weight' => $caseRubric->weight,
                        'is_essential' => $caseRubric->is_essential,
                        'note' => $caseRubric->note,
                    ])
                    ->values(),

                'repertorization_runs' => $runs
                    ->map(fn ($run) => [
                        'id' => $run->id,
                        'method' => $run->method,
                        'total_rubrics' => $run->total_rubrics,
                        'essential_rubrics_count' => $run->essential_rubrics_count,
                        'results' => $run->results
                            ->take(10)
                            ->map(fn ($result) => [
                                'rank' => $result->rank,
                                'remedy_code' => $result->remedy_code,
                                'remedy_name' => $result->remedy_name,
                                'total_score' => $result->total_score,
                                'rubric_coverage' => $result->rubric_coverage,
                                'essential_coverage' => $result->essential_coverage,
                            ])
                            ->values(),
                    ])
                    ->values(),

                'prescription' => $visit->prescription ? [
                    'remedy_code' => $visit->prescription->remedy_code,
                    'remedy_name' => $visit->prescription->remedy_name,
                    'potency' => $visit->prescription->potency,
                    'repetition' => $visit->prescription->repetition,
                    'dose_instruction' => $visit->prescription->dose_instruction,
                    'reason' => $visit->prescription->reason,
                    'advice' => $visit->prescription->advice,
                    'food_lifestyle_note' => $visit->prescription->food_lifestyle_note,
                    'follow_up_date' => $visit->prescription->follow_up_date?->toDateString(),
                    'status' => $visit->prescription->status,
                ] : null,

                'fee' => $visit->fee ? [
                    'currency' => $visit->fee->currency,
                    'consultation_fee' => $visit->fee->consultation_fee,
                    'medicine_fee' => $visit->fee->medicine_fee,
                    'discount_amount' => $visit->fee->discount_amount,
                    'total_amount' => $visit->fee->total_amount,
                    'paid_amount' => $visit->fee->paid_amount,
                    'due_amount' => $visit->fee->due_amount,
                    'payment_method' => $visit->fee->payment_method,
                    'payment_status' => $visit->fee->payment_status,
                    'payment_date' => $visit->fee->payment_date?->toDateString(),
                    'note' => $visit->fee->note,
                ] : null,
            ],
        ]);
    }

    public function prescription(Request $request, Patient $patient, PatientVisit $visit): JsonResponse
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $visit->load(['prescription']);

        return response()->json([
            'data' => [
                'document_type' => 'patient_prescription',
                'generated_at' => now()->toISOString(),

                'clinic' => $this->clinicInfo(),
                'doctor' => $this->doctorInfo($request),

                'patient' => [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'age_years' => $patient->age_years,
                    'gender' => $patient->gender,
                    'phone' => $patient->phone,
                    'address' => $patient->address,
                ],

                'visit' => [
                    'id' => $visit->id,
                    'visit_date' => $visit->visit_date?->toDateString(),
                    'chief_complaint' => $visit->chief_complaint,
                ],

                'prescription' => $visit->prescription ? [
                    'remedy_name' => $visit->prescription->remedy_name,
                    'potency' => $visit->prescription->potency,
                    'repetition' => $visit->prescription->repetition,
                    'dose_instruction' => $visit->prescription->dose_instruction,
                    'advice' => $visit->prescription->advice,
                    'food_lifestyle_note' => $visit->prescription->food_lifestyle_note,
                    'follow_up_date' => $visit->prescription->follow_up_date?->toDateString(),
                    'status' => $visit->prescription->status,
                ] : null,
            ],
        ]);
    }

    private function clinicInfo(): array
    {
        return [
            'name' => config('app.name', 'Similia AI'),
            'tagline' => 'AI Clinical Workspace for Classical Homeopathy',
            'phone' => null,
            'address' => null,
        ];
    }

    private function doctorInfo(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }

    private function ensureCanAccessVisit(Request $request, Patient $patient, PatientVisit $visit): void
    {
        $user = $request->user();

        abort_unless($visit->patient_id === $patient->id, 404);

        if ($user->role === 'admin') {
            return;
        }

        abort_unless($patient->doctor_id === $user->id, 403);
        abort_unless($visit->doctor_id === $user->id, 403);
    }
}
