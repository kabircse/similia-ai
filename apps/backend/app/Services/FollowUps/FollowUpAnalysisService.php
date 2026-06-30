<?php

namespace App\Services\FollowUps;

use App\Models\FollowUpAnalysisRun;
use App\Models\FollowUpProgressItem;
use App\Models\Patient;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FollowUpAnalysisService
{
    public function analyze(
        Patient $patient,
        PatientVisit $currentVisit,
        int $doctorId,
        ?int $previousVisitId = null,
        ?int $prescriptionId = null,
        bool $includeTimelineContext = true,
        int $limitPreviousVisits = 3
    ): FollowUpAnalysisRun {
        $previousVisit = $this->resolvePreviousVisit(
            patient: $patient,
            currentVisit: $currentVisit,
            previousVisitId: $previousVisitId
        );

        if (! $previousVisit) {
            throw new RuntimeException('No previous visit found for follow-up comparison.');
        }

        $prescription = $this->resolvePrescription(
            previousVisit: $previousVisit,
            prescriptionId: $prescriptionId
        );

        $timelineContext = $includeTimelineContext
            ? $this->timelineContext($patient, $currentVisit, $limitPreviousVisits)
            : [];

        $payload = [
            'previous_visit' => $this->visitSnapshot($previousVisit),
            'current_visit' => $this->visitSnapshot($currentVisit),
            'prescription' => $prescription ? $this->prescriptionSnapshot($prescription) : [],
            'timeline_context' => $timelineContext,
        ];

        $response = Http::timeout(config('services.ai_service.timeout'))
            ->acceptJson()
            ->post(rtrim(config('services.ai_service.url'), '/').'/follow-up/analyze', $payload);

        if ($response->failed()) {
            throw new RuntimeException('AI service failed with status '.$response->status().'.');
        }

        $analysis = $response->json('data') ?? $response->json();

        if (! is_array($analysis)) {
            throw new RuntimeException('AI service returned an invalid follow-up analysis response.');
        }

        return DB::transaction(function () use (
            $patient,
            $currentVisit,
            $previousVisit,
            $doctorId,
            $prescription,
            $payload,
            $analysis,
            $timelineContext
        ): FollowUpAnalysisRun {
            $run = FollowUpAnalysisRun::create([
                'patient_id' => $patient->id,
                'patient_visit_id' => $currentVisit->id,
                'previous_visit_id' => $previousVisit->id,
                'doctor_id' => $doctorId,
                'prescription_id' => $prescription?->id,
                'status' => 'completed',
                'response_level' => $analysis['response_level'] ?? 'unclear',
                'progress_score' => $analysis['progress_score'] ?? 0,

                'previous_case_snapshot' => $payload['previous_visit'],
                'current_case_snapshot' => $payload['current_visit'],
                'prescription_snapshot' => $payload['prescription'],

                'analysis_summary' => $analysis['analysis_summary'] ?? null,
                'remedy_response_assessment' => $analysis['remedy_response_assessment'] ?? null,

                'improvement_points' => $analysis['improvement_points'] ?? [],
                'worsening_points' => $analysis['worsening_points'] ?? [],
                'unchanged_points' => $analysis['unchanged_points'] ?? [],
                'new_symptoms' => $analysis['new_symptoms'] ?? [],
                'old_symptoms_returned' => $analysis['old_symptoms_returned'] ?? [],
                'possible_aggravation_signs' => $analysis['possible_aggravation_signs'] ?? [],
                'red_flags' => $analysis['red_flags'] ?? [],

                'suggested_follow_up_questions' => $analysis['suggested_follow_up_questions'] ?? [],
                'doctor_review_points' => $analysis['doctor_review_points'] ?? [],
                'recommended_next_steps' => $analysis['recommended_next_steps'] ?? [],

                'safety_note' => $analysis['safety_note'] ?? null,
                'metadata' => [
                    'timeline_context_count' => count($timelineContext),
                ],
            ]);

            foreach (($analysis['progress_items'] ?? []) as $item) {
                FollowUpProgressItem::create([
                    'follow_up_analysis_run_id' => $run->id,
                    'patient_id' => $patient->id,
                    'patient_visit_id' => $currentVisit->id,
                    'category' => $item['category'] ?? null,
                    'symptom' => $item['symptom'] ?? 'Unspecified symptom',
                    'change_status' => $item['change_status'] ?? 'unchanged',
                    'previous_intensity' => $item['previous_intensity'] ?? null,
                    'current_intensity' => $item['current_intensity'] ?? null,
                    'change_score' => $item['change_score'] ?? 0,
                    'evidence' => $item['evidence'] ?? null,
                    'metadata' => $item['metadata'] ?? [],
                ]);
            }

            return $run->load(['progressItems' => fn ($query) => $query->orderBy('id')]);
        });
    }

    private function resolvePreviousVisit(
        Patient $patient,
        PatientVisit $currentVisit,
        ?int $previousVisitId = null
    ): ?PatientVisit {
        if ($previousVisitId) {
            return PatientVisit::query()
                ->where('patient_id', $patient->id)
                ->where('id', $previousVisitId)
                ->where('id', '!=', $currentVisit->id)
                ->first();
        }

        return PatientVisit::query()
            ->where('patient_id', $patient->id)
            ->where('id', '!=', $currentVisit->id)
            ->where(function ($query) use ($currentVisit) {
                $query
                    ->whereDate('visit_date', '<=', $currentVisit->visit_date)
                    ->orWhere('created_at', '<', $currentVisit->created_at);
            })
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->first();
    }

    private function resolvePrescription(
        PatientVisit $previousVisit,
        ?int $prescriptionId = null
    ): ?PatientPrescription {
        if ($prescriptionId) {
            return PatientPrescription::query()
                ->where('id', $prescriptionId)
                ->where('patient_id', $previousVisit->patient_id)
                ->first();
        }

        return PatientPrescription::query()
            ->where('patient_visit_id', $previousVisit->id)
            ->latest()
            ->first();
    }

    private function timelineContext(
        Patient $patient,
        PatientVisit $currentVisit,
        int $limit = 3
    ): array {
        return PatientVisit::query()
            ->where('patient_id', $patient->id)
            ->where('id', '!=', $currentVisit->id)
            ->with('prescription')
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (PatientVisit $visit) => [
                'visit_id' => $visit->id,
                'visit_date' => $visit->visit_date?->toDateString(),
                'visit_type' => $visit->visit_type,
                'chief_complaint' => $visit->chief_complaint,
                'case_sections' => $visit->case_sections ?? [],
                'prescription' => $visit->prescription
                    ? $this->prescriptionSnapshot($visit->prescription)
                    : null,
            ])
            ->values()
            ->all();
    }

    private function visitSnapshot(PatientVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'visit_date' => $visit->visit_date?->toDateString(),
            'visit_type' => $visit->visit_type,
            'status' => $visit->status,
            'chief_complaint' => $visit->chief_complaint,
            'raw_case_text' => $visit->raw_case_text,
            'case_sections' => $visit->case_sections ?? [],
            'missing_questions' => $visit->missing_questions ?? [],
            'red_flags' => $visit->red_flags ?? [],
            'doctor_notes' => $visit->doctor_notes,
            'next_follow_up_date' => $visit->next_follow_up_date?->toDateString(),
        ];
    }

    private function prescriptionSnapshot(PatientPrescription $prescription): array
    {
        return [
            'id' => $prescription->id,
            'remedy_id' => $prescription->remedy_id,
            'remedy_code' => $prescription->remedy_code,
            'remedy_name' => $prescription->remedy_name,
            'potency' => $prescription->potency,
            'repetition' => $prescription->repetition,
            'dose_instruction' => $prescription->dose_instruction,
            'reason' => $prescription->reason,
            'advice' => $prescription->advice,
            'food_lifestyle_note' => $prescription->food_lifestyle_note,
            'follow_up_date' => $prescription->follow_up_date?->toDateString(),
            'status' => $prescription->status,
        ];
    }
}
