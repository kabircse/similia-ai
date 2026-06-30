<?php

namespace App\Services\Suggestions;

use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\RemedySuggestionItem;
use App\Models\RemedySuggestionRun;
use App\Services\Remedies\RemedyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class RemedySuggestionGenerator
{
    public function __construct(
        private readonly RemedySuggestionEvidenceBuilder $evidenceBuilder,
        private readonly RemedyResolver $remedyResolver
    ) {}

    public function generate(
        Patient $patient,
        PatientVisit $visit,
        int $doctorId,
        ?int $repertorizationRunId = null,
        ?string $method = null,
        int $limit = 3,
        array $settings = []
    ): RemedySuggestionRun {
        $evidence = $this->evidenceBuilder->build(
            visit: $visit,
            repertorizationRunId: $repertorizationRunId,
            method: $method,
            limit: $limit,
            settings: $settings
        );

        $response = Http::timeout(config('services.ai_service.timeout'))
            ->acceptJson()
            ->post(rtrim(config('services.ai_service.url'), '/').'/remedy/suggest', $evidence);

        if ($response->failed()) {
            throw new \RuntimeException('AI service failed with status '.$response->status());
        }

        $suggestion = $response->json('data') ?? $response->json();

        if (! is_array($suggestion)) {
            throw new \RuntimeException('AI service returned an invalid remedy suggestion response.');
        }

        return DB::transaction(function () use (
            $patient,
            $visit,
            $doctorId,
            $limit,
            $settings,
            $evidence,
            $suggestion,
            $method
        ): RemedySuggestionRun {
            $run = RemedySuggestionRun::create([
                'patient_id' => $patient->id,
                'patient_visit_id' => $visit->id,
                'doctor_id' => $doctorId,
                'repertorization_run_id' => $evidence['repertorization_run']['id'] ?? null,
                'method' => $evidence['repertorization_run']['method'] ?? $method,
                'status' => 'completed',
                'limit' => $limit,
                'case_snapshot' => $evidence['case_snapshot'] ?? [],
                'selected_rubrics_snapshot' => $evidence['selected_rubrics'] ?? [],
                'retrieved_sources' => $evidence['retrieved_sources'] ?? [],
                'settings' => $settings,
                'safety_note' => $suggestion['safety_note'] ?? null,
            ]);

            foreach (($suggestion['suggestions'] ?? []) as $item) {
                $resolvedRemedy = $this->remedyResolver->findByText($item['remedy_code'] ?? null)
                    ?: $this->remedyResolver->findByText($item['remedy_name'] ?? null);

                RemedySuggestionItem::create([
                    'remedy_suggestion_run_id' => $run->id,
                    'remedy_id' => $resolvedRemedy?->id,
                    'remedy_code' => $item['remedy_code'] ?? $resolvedRemedy?->code,
                    'remedy_name' => $item['remedy_name'] ?? $resolvedRemedy?->name ?? 'Unknown remedy',
                    'rank' => $item['rank'] ?? 1,
                    'confidence_score' => $item['confidence_score'] ?? 0,
                    'repertory_score' => $item['repertory_score'] ?? 0,
                    'materia_medica_score' => $item['materia_medica_score'] ?? 0,
                    'knowledge_score' => $item['knowledge_score'] ?? 0,
                    'summary' => $item['summary'] ?? null,
                    'matching_points' => $item['matching_points'] ?? [],
                    'differentiating_points' => $item['differentiating_points'] ?? [],
                    'missing_questions' => $item['missing_questions'] ?? [],
                    'evidence_matrix' => $item['evidence_matrix'] ?? [],
                    'repertory_evidence' => $item['repertory_evidence'] ?? [],
                    'materia_medica_evidence' => $item['materia_medica_evidence'] ?? [],
                    'potency_considerations' => $item['potency_considerations'] ?? [],
                    'relationship_notes' => $item['relationship_notes'] ?? [],
                    'medical_safety_notes' => $item['medical_safety_notes'] ?? [],
                    'source_chunks' => $item['source_chunks'] ?? [],
                    'metadata' => $item['metadata'] ?? [],
                ]);
            }

            return $run->load(['items' => fn ($query) => $query->orderBy('rank')]);
        });
    }
}
