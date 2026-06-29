<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\RepertorizationRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientTimelineController extends Controller
{
    public function index(Request $request, Patient $patient): JsonResponse
    {
        $this->ensureCanAccessPatient($request, $patient);

        $visits = PatientVisit::query()
            ->with([
                'prescription',
                'fee',
                'caseRubrics.rubric',
            ])
            ->where('patient_id', $patient->id)
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10));

        $items = $visits->getCollection()
            ->map(function (PatientVisit $visit) {
                $latestRuns = RepertorizationRun::query()
                    ->with(['results' => fn ($query) => $query->orderBy('rank')])
                    ->where('patient_visit_id', $visit->id)
                    ->latest()
                    ->limit(3)
                    ->get();

                return [
                    'id' => 'visit-'.$visit->id,
                    'type' => 'visit',
                    'date' => $visit->visit_date?->toDateString(),
                    'title' => $this->buildTitle($visit),

                    'visit' => [
                        'id' => $visit->id,
                        'visit_date' => $visit->visit_date?->toDateString(),
                        'visit_type' => $visit->visit_type,
                        'status' => $visit->status,
                        'case_source' => $visit->case_source,
                        'chief_complaint' => $visit->chief_complaint,
                        'doctor_notes' => $visit->doctor_notes,
                        'next_follow_up_date' => $visit->next_follow_up_date?->toDateString(),
                    ],

                    'case_summary' => [
                        'rubrics_count' => $visit->caseRubrics->count(),
                        'essential_rubrics_count' => $visit->caseRubrics
                            ->where('is_essential', true)
                            ->count(),

                        'selected_rubrics' => $visit->caseRubrics
                            ->take(5)
                            ->map(fn ($caseRubric) => [
                                'rubric_path' => $caseRubric->rubric?->rubric_path,
                                'symptom_type' => $caseRubric->symptom_type,
                                'importance' => $caseRubric->importance,
                                'weight' => $caseRubric->weight,
                                'is_essential' => (bool) $caseRubric->is_essential,
                            ])
                            ->values(),
                    ],

                    'repertorization' => $latestRuns
                        ->map(fn ($run) => [
                            'id' => $run->id,
                            'method' => $run->method,
                            'created_at' => $run->created_at?->toISOString(),
                            'top_results' => $run->results
                                ->take(3)
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
                        'id' => $visit->prescription->id,
                        'remedy_code' => $visit->prescription->remedy_code,
                        'remedy_name' => $visit->prescription->remedy_name,
                        'potency' => $visit->prescription->potency,
                        'repetition' => $visit->prescription->repetition,
                        'follow_up_date' => $visit->prescription->follow_up_date?->toDateString(),
                        'status' => $visit->prescription->status,
                    ] : null,

                    'fee' => $visit->fee ? [
                        'id' => $visit->fee->id,
                        'currency' => $visit->fee->currency,
                        'total_amount' => $visit->fee->total_amount,
                        'paid_amount' => $visit->fee->paid_amount,
                        'due_amount' => $visit->fee->due_amount,
                        'payment_status' => $visit->fee->payment_status,
                        'payment_method' => $visit->fee->payment_method,
                        'payment_date' => $visit->fee->payment_date?->toDateString(),
                    ] : null,
                ];
            })
            ->values();

        $visits->setCollection($items);

        return response()->json([
            'data' => $visits->items(),
            'meta' => [
                'patient' => [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'age_years' => $patient->age_years,
                    'gender' => $patient->gender,
                    'phone' => $patient->phone,
                ],
                'current_page' => $visits->currentPage(),
                'last_page' => $visits->lastPage(),
                'per_page' => $visits->perPage(),
                'total' => $visits->total(),
            ],
        ]);
    }

    private function buildTitle(PatientVisit $visit): string
    {
        $date = $visit->visit_date?->toDateString() ?? 'Unknown date';
        $type = ucfirst(str_replace('_', ' ', $visit->visit_type));

        return "{$type} visit - {$date}";
    }

    private function ensureCanAccessPatient(Request $request, Patient $patient): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        abort_unless($patient->doctor_id === $user->id, 403);
    }
}
