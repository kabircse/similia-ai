<?php

namespace App\Services\RemedyRelationships;

use App\Models\FollowUpAnalysisRun;
use App\Models\KnowledgeChunk;
use App\Models\Patient;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\Remedy;
use App\Models\RemedyRelationshipFinding;
use App\Models\RemedyRelationshipRun;
use App\Services\Knowledge\SimpleTextEmbedding;
use App\Services\Remedies\RemedyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RemedyRelationshipService
{
    public function __construct(
        private readonly SimpleTextEmbedding $embedding,
        private readonly RemedyResolver $remedyResolver
    ) {}

    public function generate(
        Patient $patient,
        PatientVisit $visit,
        int $doctorId,
        ?int $primaryRemedyId = null,
        ?string $primaryRemedyCode = null,
        ?string $primaryRemedyName = null,
        ?int $comparisonRemedyId = null,
        ?string $comparisonRemedyCode = null,
        ?string $comparisonRemedyName = null,
        string $purpose = 'general',
        ?int $prescriptionId = null,
        bool $includeVisitContext = true,
        bool $includeFollowUpContext = true,
        string $responseLanguage = 'auto'
    ): RemedyRelationshipRun {
        $prescription = $this->resolvePrescription($patient, $visit, $prescriptionId);

        $primaryRemedy = $this->resolveRemedy(
            remedyId: $primaryRemedyId,
            remedyCode: $primaryRemedyCode ?: $prescription?->remedy_code,
            remedyName: $primaryRemedyName ?: $prescription?->remedy_name
        );

        $comparisonRemedy = $this->resolveRemedy(
            remedyId: $comparisonRemedyId,
            remedyCode: $comparisonRemedyCode,
            remedyName: $comparisonRemedyName
        );

        $primaryRemedyName = $primaryRemedy?->name ?: $primaryRemedyName ?: $prescription?->remedy_name;
        $primaryRemedyCode = $primaryRemedy?->code ?: $primaryRemedyCode ?: $prescription?->remedy_code;
        $comparisonRemedyName = $comparisonRemedy?->name ?: $comparisonRemedyName;
        $comparisonRemedyCode = $comparisonRemedy?->code ?: $comparisonRemedyCode;

        if (! $primaryRemedyName) {
            throw new RuntimeException('Primary remedy is required for relationship guidance.');
        }

        $followUp = $includeFollowUpContext ? $this->latestFollowUpAnalysis($visit) : null;
        $caseSnapshot = $includeVisitContext ? $this->visitSnapshot($visit) : [];
        $prescriptionSnapshot = $prescription ? $this->prescriptionSnapshot($prescription) : [];
        $followUpSnapshot = $followUp ? $this->followUpSnapshot($followUp) : [];
        $settings = [
            'include_visit_context' => $includeVisitContext,
            'include_follow_up_context' => $includeFollowUpContext,
            'response_language' => $responseLanguage,
        ];

        $knowledgeChunks = $this->retrieveRelationshipChunks(
            visit: $visit,
            primaryRemedyName: $primaryRemedyName,
            comparisonRemedyName: $comparisonRemedyName,
            prescriptionSnapshot: $prescriptionSnapshot,
            followUpSnapshot: $followUpSnapshot,
            settings: $settings
        );

        $payload = [
            'primary_remedy' => [
                'remedy_id' => $primaryRemedy?->id,
                'remedy_code' => $primaryRemedyCode,
                'remedy_name' => $primaryRemedyName,
            ],
            'comparison_remedy' => [
                'remedy_id' => $comparisonRemedy?->id,
                'remedy_code' => $comparisonRemedyCode,
                'remedy_name' => $comparisonRemedyName,
            ],
            'purpose' => $purpose,
            'case_snapshot' => $this->emptyObjectWhenBlank($caseSnapshot),
            'prescription_snapshot' => $this->emptyObjectWhenBlank($prescriptionSnapshot),
            'follow_up_snapshot' => $this->emptyObjectWhenBlank($followUpSnapshot),
            'knowledge_chunks' => $knowledgeChunks,
            'response_language' => $responseLanguage,
        ];

        $response = Http::timeout(config('services.ai_service.timeout'))
            ->acceptJson()
            ->post(rtrim(config('services.ai_service.url'), '/').'/remedy/relationship', $payload);

        if ($response->failed()) {
            throw new RuntimeException('AI service failed with status '.$response->status().'.');
        }

        $guidance = $response->json('data') ?? $response->json();

        if (! is_array($guidance)) {
            throw new RuntimeException('AI service returned an invalid remedy relationship response.');
        }

        return DB::transaction(function () use (
            $patient,
            $visit,
            $doctorId,
            $primaryRemedy,
            $primaryRemedyCode,
            $primaryRemedyName,
            $comparisonRemedy,
            $comparisonRemedyCode,
            $comparisonRemedyName,
            $purpose,
            $responseLanguage,
            $caseSnapshot,
            $prescriptionSnapshot,
            $followUpSnapshot,
            $knowledgeChunks,
            $settings,
            $guidance
        ): RemedyRelationshipRun {
            $run = RemedyRelationshipRun::create([
                'patient_id' => $patient->id,
                'patient_visit_id' => $visit->id,
                'doctor_id' => $doctorId,
                'primary_remedy_id' => $primaryRemedy?->id,
                'primary_remedy_code' => $primaryRemedyCode,
                'primary_remedy_name' => $primaryRemedyName,
                'comparison_remedy_id' => $comparisonRemedy?->id,
                'comparison_remedy_code' => $comparisonRemedyCode,
                'comparison_remedy_name' => $comparisonRemedyName,
                'purpose' => $purpose,
                'status' => 'completed',
                'response_language' => $responseLanguage,
                'case_snapshot' => $caseSnapshot,
                'prescription_snapshot' => $prescriptionSnapshot,
                'follow_up_snapshot' => $followUpSnapshot,
                'retrieved_sources' => [
                    'knowledge_chunks_count' => count($knowledgeChunks),
                    'source_types' => collect($knowledgeChunks)->pluck('source_type')->unique()->values()->all(),
                ],
                'settings' => $settings,
                'relationship_summary' => $guidance['relationship_summary'] ?? null,
                'sequence_guidance' => $guidance['sequence_guidance'] ?? null,
                'antidote_guidance' => $guidance['antidote_guidance'] ?? null,
                'inimical_warning' => $guidance['inimical_warning'] ?? null,
                'complementary_note' => $guidance['complementary_note'] ?? null,
                'cautions' => $guidance['cautions'] ?? [],
                'doctor_review_points' => $guidance['doctor_review_points'] ?? [],
                'suggested_questions' => $guidance['suggested_questions'] ?? [],
                'safety_note' => $guidance['safety_note'] ?? null,
                'metadata' => [
                    'has_comparison_remedy' => (bool) $comparisonRemedyName,
                    'findings_count' => count($guidance['findings'] ?? []),
                ],
            ]);

            foreach (($guidance['findings'] ?? []) as $finding) {
                $relatedRemedy = $this->resolveRemedy(
                    remedyId: null,
                    remedyCode: $finding['related_remedy_code'] ?? null,
                    remedyName: $finding['related_remedy_name'] ?? null
                );

                RemedyRelationshipFinding::create([
                    'remedy_relationship_run_id' => $run->id,
                    'related_remedy_id' => $relatedRemedy?->id,
                    'related_remedy_code' => $relatedRemedy?->code ?: ($finding['related_remedy_code'] ?? null),
                    'related_remedy_name' => $relatedRemedy?->name ?: ($finding['related_remedy_name'] ?? null),
                    'relationship_type' => $finding['relationship_type'] ?? 'unknown',
                    'direction' => $finding['direction'] ?? null,
                    'rank' => $finding['rank'] ?? 1,
                    'confidence_score' => $finding['confidence_score'] ?? 0,
                    'summary' => $finding['summary'] ?? null,
                    'clinical_note' => $finding['clinical_note'] ?? null,
                    'caution' => $finding['caution'] ?? null,
                    'evidence' => $finding['evidence'] ?? [],
                    'source_chunks' => $finding['source_chunks'] ?? [],
                    'metadata' => $finding['metadata'] ?? [],
                ]);
            }

            return $run->load(['findings' => fn ($query) => $query->orderBy('rank')]);
        });
    }

    private function resolvePrescription(
        Patient $patient,
        PatientVisit $visit,
        ?int $prescriptionId = null
    ): ?PatientPrescription {
        if ($prescriptionId) {
            return PatientPrescription::query()
                ->where('patient_id', $patient->id)
                ->where('id', $prescriptionId)
                ->first();
        }

        return PatientPrescription::query()
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->first();
    }

    private function resolveRemedy(
        ?int $remedyId,
        ?string $remedyCode,
        ?string $remedyName
    ): ?Remedy {
        if ($remedyId) {
            return Remedy::find($remedyId);
        }

        return $this->remedyResolver->findByText($remedyCode)
            ?: $this->remedyResolver->findByText($remedyName);
    }

    private function latestFollowUpAnalysis(PatientVisit $visit): ?FollowUpAnalysisRun
    {
        return FollowUpAnalysisRun::query()
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->first();
    }

    private function retrieveRelationshipChunks(
        PatientVisit $visit,
        string $primaryRemedyName,
        ?string $comparisonRemedyName,
        array $prescriptionSnapshot,
        array $followUpSnapshot,
        array $settings
    ): array {
        $queryText = trim(implode("\n", array_filter([
            'Homeopathic remedy relationship complementary follows well followed by inimical antidote sequence',
            "Primary remedy: {$primaryRemedyName}",
            $comparisonRemedyName ? "Comparison remedy: {$comparisonRemedyName}" : null,
            $settings['include_visit_context'] ? $visit->chief_complaint : null,
            $settings['include_visit_context'] ? $visit->raw_case_text : null,
            $settings['include_visit_context'] ? $this->stringifyCaseSections($visit->case_sections ?? []) : null,
            $this->stringifyValue($prescriptionSnapshot),
            $this->stringifyValue($followUpSnapshot),
        ])));

        $queryVector = $this->embedding->toPgVector(
            $this->embedding->embed($queryText)
        );

        $chunks = KnowledgeChunk::query()
            ->with('knowledgeSource')
            ->select('knowledge_chunks.*')
            ->selectRaw('embedding <=> ?::vector as distance', [$queryVector])
            ->where('source_type', 'relationship')
            ->whereNotNull('embedding')
            ->orderByRaw('embedding <=> ?::vector', [$queryVector])
            ->limit(16)
            ->get();

        return $chunks->map(fn (KnowledgeChunk $chunk) => [
            'id' => $chunk->id,
            'source_type' => $chunk->source_type,
            'book_code' => $chunk->book_code,
            'source_title' => $chunk->knowledgeSource?->title,
            'author' => $chunk->knowledgeSource?->author,
            'edition' => $chunk->knowledgeSource?->edition,
            'title' => $chunk->title,
            'section_no' => $chunk->section_no,
            'content' => $chunk->content,
            'source_ref' => $chunk->source_ref,
            'distance' => isset($chunk->distance) ? (float) $chunk->distance : null,
        ])->values()->all();
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

    private function followUpSnapshot(FollowUpAnalysisRun $run): array
    {
        return [
            'id' => $run->id,
            'response_level' => $run->response_level,
            'progress_score' => $run->progress_score,
            'analysis_summary' => $run->analysis_summary,
            'remedy_response_assessment' => $run->remedy_response_assessment,
            'improvement_points' => $run->improvement_points ?? [],
            'worsening_points' => $run->worsening_points ?? [],
            'new_symptoms' => $run->new_symptoms ?? [],
            'possible_aggravation_signs' => $run->possible_aggravation_signs ?? [],
            'red_flags' => $run->red_flags ?? [],
        ];
    }

    private function stringifyCaseSections(array $sections): string
    {
        return collect($sections)
            ->filter()
            ->map(fn ($value, $key) => str_replace('_', ' ', $key).': '.$this->stringifyValue($value))
            ->join("\n");
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item, $key) => is_string($key)
                    ? $key.': '.$this->stringifyValue($item)
                    : $this->stringifyValue($item))
                ->filter()
                ->join('; ');
        }

        return trim((string) $value);
    }

    private function emptyObjectWhenBlank(array $value): array|object
    {
        if ($value === []) {
            return new \stdClass;
        }

        return $value;
    }
}
