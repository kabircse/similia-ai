<?php

namespace App\Services\PrescriptionReviews;

use App\Models\FollowUpAnalysisRun;
use App\Models\Patient;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\PotencyGuidanceRun;
use App\Models\PrescriptionReviewCheck;
use App\Models\PrescriptionReviewRun;
use App\Models\RemedyRelationshipRun;
use App\Models\RemedySuggestionRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PrescriptionReviewService
{
    public function generate(
        Patient $patient,
        PatientVisit $visit,
        int $doctorId,
        ?int $prescriptionId = null,
        bool $includeRemedySuggestion = true,
        bool $includePotencyGuidance = true,
        bool $includeRelationshipGuidance = true,
        bool $includeFollowUpAnalysis = true,
        string $responseLanguage = 'auto'
    ): PrescriptionReviewRun {
        $prescription = $this->resolvePrescription($patient, $visit, $prescriptionId);

        if (! $prescription) {
            throw new RuntimeException('Save a draft prescription before generating prescription review.');
        }

        $remedySuggestion = $includeRemedySuggestion ? $this->latestRemedySuggestion($visit) : null;
        $potencyGuidance = $includePotencyGuidance ? $this->latestPotencyGuidance($visit) : null;
        $relationshipGuidance = $includeRelationshipGuidance ? $this->latestRelationshipGuidance($visit) : null;
        $followUpAnalysis = $includeFollowUpAnalysis ? $this->latestFollowUpAnalysis($visit) : null;

        $caseSnapshot = $this->visitSnapshot($visit);
        $prescriptionSnapshot = $this->prescriptionSnapshot($prescription);
        $remedySuggestionSnapshot = $remedySuggestion ? $this->remedySuggestionSnapshot($remedySuggestion) : [];
        $potencyGuidanceSnapshot = $potencyGuidance ? $this->potencyGuidanceSnapshot($potencyGuidance) : [];
        $relationshipSnapshot = $relationshipGuidance ? $this->relationshipSnapshot($relationshipGuidance) : [];
        $followUpSnapshot = $followUpAnalysis ? $this->followUpSnapshot($followUpAnalysis) : [];

        $payload = [
            'case_snapshot' => $caseSnapshot,
            'prescription_snapshot' => $prescriptionSnapshot,
            'remedy_suggestion_snapshot' => $this->emptyObjectWhenBlank($remedySuggestionSnapshot),
            'potency_guidance_snapshot' => $this->emptyObjectWhenBlank($potencyGuidanceSnapshot),
            'relationship_snapshot' => $this->emptyObjectWhenBlank($relationshipSnapshot),
            'follow_up_snapshot' => $this->emptyObjectWhenBlank($followUpSnapshot),
            'response_language' => $responseLanguage,
        ];

        $response = Http::timeout(config('services.ai_service.timeout'))
            ->acceptJson()
            ->post(rtrim(config('services.ai_service.url'), '/').'/prescription/review', $payload);

        if ($response->failed()) {
            throw new RuntimeException('AI service failed with status '.$response->status().'.');
        }

        $review = $response->json('data') ?? $response->json();

        if (! is_array($review)) {
            throw new RuntimeException('AI service returned an invalid prescription review response.');
        }

        return DB::transaction(function () use (
            $patient,
            $visit,
            $doctorId,
            $prescription,
            $responseLanguage,
            $caseSnapshot,
            $prescriptionSnapshot,
            $remedySuggestionSnapshot,
            $potencyGuidanceSnapshot,
            $relationshipSnapshot,
            $followUpSnapshot,
            $review
        ): PrescriptionReviewRun {
            $run = PrescriptionReviewRun::create([
                'patient_id' => $patient->id,
                'patient_visit_id' => $visit->id,
                'doctor_id' => $doctorId,
                'prescription_id' => $prescription->id,
                'remedy_id' => $prescription->remedy_id,
                'remedy_code' => $prescription->remedy_code,
                'remedy_name' => $prescription->remedy_name,
                'potency' => $prescription->potency,
                'repetition' => $prescription->repetition,
                'status' => 'completed',
                'review_status' => $review['review_status'] ?? 'needs_doctor_review',
                'safety_score' => $review['safety_score'] ?? 0,
                'response_language' => $responseLanguage,
                'case_snapshot' => $caseSnapshot,
                'prescription_snapshot' => $prescriptionSnapshot,
                'remedy_suggestion_snapshot' => $remedySuggestionSnapshot,
                'potency_guidance_snapshot' => $potencyGuidanceSnapshot,
                'relationship_snapshot' => $relationshipSnapshot,
                'follow_up_snapshot' => $followUpSnapshot,
                'review_summary' => $review['review_summary'] ?? null,
                'decision_guidance' => $review['decision_guidance'] ?? null,
                'risk_summary' => $review['risk_summary'] ?? null,
                'red_flags' => $review['red_flags'] ?? [],
                'missing_information' => $review['missing_information'] ?? [],
                'doctor_review_points' => $review['doctor_review_points'] ?? [],
                'recommended_actions' => $review['recommended_actions'] ?? [],
                'safety_note' => $review['safety_note'] ?? null,
                'metadata' => [
                    'checks_count' => count($review['checks'] ?? []),
                    'has_remedy_suggestion' => $remedySuggestionSnapshot !== [],
                    'has_potency_guidance' => $potencyGuidanceSnapshot !== [],
                    'has_relationship_guidance' => $relationshipSnapshot !== [],
                    'has_follow_up_analysis' => $followUpSnapshot !== [],
                ],
            ]);

            foreach (($review['checks'] ?? []) as $check) {
                PrescriptionReviewCheck::create([
                    'prescription_review_run_id' => $run->id,
                    'doctor_id' => $doctorId,
                    'check_key' => $check['check_key'] ?? 'review_check',
                    'category' => $check['category'] ?? 'general',
                    'severity' => $check['severity'] ?? 'normal',
                    'status' => $check['status'] ?? 'pending',
                    'is_required' => $check['is_required'] ?? true,
                    'is_blocking' => $check['is_blocking'] ?? false,
                    'title' => $check['title'] ?? 'Prescription review check',
                    'description' => $check['description'] ?? null,
                    'ai_assessment' => $check['ai_assessment'] ?? null,
                    'evidence' => $check['evidence'] ?? [],
                    'metadata' => $check['metadata'] ?? [],
                ]);
            }

            return $run->load(['checks' => fn ($query) => $query->orderBy('id')]);
        });
    }

    public function updateCheck(
        PrescriptionReviewRun $run,
        PrescriptionReviewCheck $check,
        int $doctorId,
        string $status,
        ?string $doctorNote = null
    ): PrescriptionReviewRun {
        return DB::transaction(function () use ($run, $check, $doctorId, $status, $doctorNote): PrescriptionReviewRun {
            $check->update([
                'doctor_id' => $doctorId,
                'status' => $status,
                'doctor_note' => $doctorNote,
                'doctor_confirmed_at' => in_array($status, ['doctor_confirmed', 'doctor_overridden'], true)
                    ? now()
                    : null,
            ]);

            $run->update([
                'review_status' => $this->reviewStatusFromChecks($run->fresh()->checks()->get()),
            ]);

            return $run->fresh()->load(['checks' => fn ($query) => $query->orderBy('id')]);
        });
    }

    private function reviewStatusFromChecks($checks): string
    {
        $accepted = ['passed', 'doctor_confirmed', 'doctor_overridden'];

        if ($checks->contains(fn ($check) => $check->is_blocking && ! in_array($check->status, $accepted, true))) {
            return 'blocked';
        }

        if ($checks->contains(fn ($check) => in_array($check->status, ['failed', 'warning'], true))) {
            return 'safety_warning';
        }

        if ($checks->contains(fn ($check) => $check->is_required && ! in_array($check->status, $accepted, true))) {
            return 'needs_doctor_review';
        }

        return 'ready';
    }

    private function resolvePrescription(
        Patient $patient,
        PatientVisit $visit,
        ?int $prescriptionId = null
    ): ?PatientPrescription {
        if ($prescriptionId) {
            return PatientPrescription::query()
                ->where('patient_id', $patient->id)
                ->where('patient_visit_id', $visit->id)
                ->where('id', $prescriptionId)
                ->first();
        }

        return PatientPrescription::query()
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->first();
    }

    private function latestRemedySuggestion(PatientVisit $visit): ?RemedySuggestionRun
    {
        return RemedySuggestionRun::query()
            ->with(['items' => fn ($query) => $query->orderBy('rank')])
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->first();
    }

    private function latestPotencyGuidance(PatientVisit $visit): ?PotencyGuidanceRun
    {
        return PotencyGuidanceRun::query()
            ->with(['options' => fn ($query) => $query->orderBy('rank')])
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->first();
    }

    private function latestRelationshipGuidance(PatientVisit $visit): ?RemedyRelationshipRun
    {
        return RemedyRelationshipRun::query()
            ->with(['findings' => fn ($query) => $query->orderBy('rank')])
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->first();
    }

    private function latestFollowUpAnalysis(PatientVisit $visit): ?FollowUpAnalysisRun
    {
        return FollowUpAnalysisRun::query()
            ->with(['progressItems' => fn ($query) => $query->orderBy('id')])
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->first();
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

    private function remedySuggestionSnapshot(RemedySuggestionRun $run): array
    {
        return [
            'id' => $run->id,
            'method' => $run->method,
            'safety_note' => $run->safety_note,
            'items' => $run->items->take(5)->map(fn ($item) => [
                'remedy_code' => $item->remedy_code,
                'remedy_name' => $item->remedy_name,
                'rank' => $item->rank,
                'confidence_score' => $item->confidence_score,
                'summary' => $item->summary,
                'matching_points' => $item->matching_points ?? [],
                'differentiating_points' => $item->differentiating_points ?? [],
                'missing_questions' => $item->missing_questions ?? [],
            ])->values()->all(),
        ];
    }

    private function potencyGuidanceSnapshot(PotencyGuidanceRun $run): array
    {
        return [
            'id' => $run->id,
            'case_phase' => $run->case_phase,
            'vitality_level' => $run->vitality_level,
            'sensitivity_level' => $run->sensitivity_level,
            'pathology_depth' => $run->pathology_depth,
            'guidance_summary' => $run->guidance_summary,
            'repetition_guidance' => $run->repetition_guidance,
            'cautions' => $run->cautions ?? [],
            'options' => $run->options->take(5)->map(fn ($option) => [
                'potency_range' => $option->potency_range,
                'potency_label' => $option->potency_label,
                'suitability_score' => $option->suitability_score,
                'caution' => $option->caution,
            ])->values()->all(),
        ];
    }

    private function relationshipSnapshot(RemedyRelationshipRun $run): array
    {
        return [
            'id' => $run->id,
            'primary_remedy_name' => $run->primary_remedy_name,
            'comparison_remedy_name' => $run->comparison_remedy_name,
            'purpose' => $run->purpose,
            'relationship_summary' => $run->relationship_summary,
            'inimical_warning' => $run->inimical_warning,
            'cautions' => $run->cautions ?? [],
            'findings' => $run->findings->take(5)->map(fn ($finding) => [
                'related_remedy_name' => $finding->related_remedy_name,
                'relationship_type' => $finding->relationship_type,
                'summary' => $finding->summary,
                'caution' => $finding->caution,
            ])->values()->all(),
        ];
    }

    private function followUpSnapshot(FollowUpAnalysisRun $run): array
    {
        return [
            'id' => $run->id,
            'response_level' => $run->response_level,
            'progress_score' => $run->progress_score,
            'analysis_summary' => $run->analysis_summary,
            'red_flags' => $run->red_flags ?? [],
            'recommended_next_steps' => $run->recommended_next_steps ?? [],
        ];
    }

    private function emptyObjectWhenBlank(array $value): array|object
    {
        if ($value === []) {
            return new \stdClass;
        }

        return $value;
    }
}
