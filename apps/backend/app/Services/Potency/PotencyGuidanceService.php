<?php

namespace App\Services\Potency;

use App\Models\FollowUpAnalysisRun;
use App\Models\KnowledgeChunk;
use App\Models\Patient;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\PotencyGuidanceOption;
use App\Models\PotencyGuidanceRun;
use App\Models\Remedy;
use App\Services\Knowledge\SimpleTextEmbedding;
use App\Services\Remedies\RemedyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PotencyGuidanceService
{
    public function __construct(
        private readonly SimpleTextEmbedding $embedding,
        private readonly RemedyResolver $remedyResolver
    ) {}

    public function generate(
        Patient $patient,
        PatientVisit $visit,
        int $doctorId,
        ?int $prescriptionId = null,
        ?int $remedyId = null,
        ?string $remedyName = null,
        ?string $remedyCode = null,
        array $settings = []
    ): PotencyGuidanceRun {
        $prescription = $this->resolvePrescription($patient, $visit, $prescriptionId);

        $remedy = $this->resolveRemedy(
            remedyId: $remedyId,
            remedyCode: $remedyCode ?: $prescription?->remedy_code,
            remedyName: $remedyName ?: $prescription?->remedy_name
        );

        $remedyName = $remedy?->name ?: $remedyName ?: $prescription?->remedy_name;
        $remedyCode = $remedy?->code ?: $remedyCode ?: $prescription?->remedy_code;

        $followUp = ($settings['include_follow_up_context'] ?? true)
            ? $this->latestFollowUpAnalysis($visit)
            : null;

        $caseSnapshot = $this->visitSnapshot($visit);
        $prescriptionSnapshot = $prescription ? $this->prescriptionSnapshot($prescription) : [];
        $followUpSnapshot = $followUp ? $this->followUpSnapshot($followUp) : [];

        $knowledgeChunks = $this->retrieveKnowledgeChunks(
            visit: $visit,
            remedyName: $remedyName,
            settings: $settings
        );

        $payload = [
            'case_snapshot' => $caseSnapshot,
            'prescription_snapshot' => $this->emptyObjectWhenBlank($prescriptionSnapshot),
            'follow_up_snapshot' => $this->emptyObjectWhenBlank($followUpSnapshot),
            'remedy' => [
                'remedy_id' => $remedy?->id,
                'remedy_code' => $remedyCode,
                'remedy_name' => $remedyName,
            ],
            'settings' => $settings,
            'knowledge_chunks' => $knowledgeChunks,
        ];

        $response = Http::timeout(config('services.ai_service.timeout'))
            ->acceptJson()
            ->post(rtrim(config('services.ai_service.url'), '/').'/potency/guidance', $payload);

        if ($response->failed()) {
            throw new RuntimeException('AI service failed with status '.$response->status().'.');
        }

        $guidance = $response->json('data') ?? $response->json();

        if (! is_array($guidance)) {
            throw new RuntimeException('AI service returned an invalid potency guidance response.');
        }

        return DB::transaction(function () use (
            $patient,
            $visit,
            $doctorId,
            $prescription,
            $remedy,
            $remedyCode,
            $remedyName,
            $settings,
            $caseSnapshot,
            $prescriptionSnapshot,
            $followUpSnapshot,
            $knowledgeChunks,
            $followUp,
            $guidance
        ): PotencyGuidanceRun {
            $run = PotencyGuidanceRun::create([
                'patient_id' => $patient->id,
                'patient_visit_id' => $visit->id,
                'doctor_id' => $doctorId,
                'prescription_id' => $prescription?->id,
                'remedy_id' => $remedy?->id,
                'remedy_code' => $remedyCode,
                'remedy_name' => $remedyName,
                'case_phase' => $guidance['case_phase'] ?? ($settings['case_phase'] ?? 'unclear'),
                'status' => 'completed',
                'case_snapshot' => $caseSnapshot,
                'prescription_snapshot' => $prescriptionSnapshot,
                'follow_up_snapshot' => $followUpSnapshot,
                'retrieved_sources' => [
                    'knowledge_chunks_count' => count($knowledgeChunks),
                    'source_types' => collect($knowledgeChunks)->pluck('source_type')->unique()->values()->all(),
                ],
                'settings' => $settings,
                'vitality_level' => $guidance['vitality_level'] ?? null,
                'sensitivity_level' => $guidance['sensitivity_level'] ?? null,
                'pathology_depth' => $guidance['pathology_depth'] ?? null,
                'guidance_summary' => $guidance['guidance_summary'] ?? null,
                'repetition_guidance' => $guidance['repetition_guidance'] ?? null,
                'wait_and_watch_guidance' => $guidance['wait_and_watch_guidance'] ?? null,
                'aggravation_guidance' => $guidance['aggravation_guidance'] ?? null,
                'cautions' => $guidance['cautions'] ?? [],
                'follow_up_questions' => $guidance['follow_up_questions'] ?? [],
                'doctor_review_points' => $guidance['doctor_review_points'] ?? [],
                'safety_note' => $guidance['safety_note'] ?? null,
                'metadata' => [
                    'has_prescription' => (bool) $prescription,
                    'has_follow_up_analysis' => (bool) $followUp,
                ],
            ]);

            foreach (($guidance['options'] ?? []) as $option) {
                PotencyGuidanceOption::create([
                    'potency_guidance_run_id' => $run->id,
                    'potency_range' => $option['potency_range'] ?? 'unclear',
                    'potency_label' => $option['potency_label'] ?? null,
                    'rank' => $option['rank'] ?? 1,
                    'suitability_score' => $option['suitability_score'] ?? 0,
                    'rationale' => $option['rationale'] ?? null,
                    'repetition_note' => $option['repetition_note'] ?? null,
                    'caution' => $option['caution'] ?? null,
                    'source_chunks' => $option['source_chunks'] ?? [],
                    'metadata' => $option['metadata'] ?? [],
                ]);
            }

            return $run->load(['options' => fn ($query) => $query->orderBy('rank')]);
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

    private function retrieveKnowledgeChunks(
        PatientVisit $visit,
        ?string $remedyName,
        array $settings
    ): array {
        $queryText = trim(implode("\n", array_filter([
            'Potency repetition dose wait and watch homeopathic aggravation',
            $remedyName ? "Remedy: {$remedyName}" : null,
            $visit->chief_complaint,
            $visit->raw_case_text,
            $this->stringifyCaseSections($visit->case_sections ?? []),
        ])));

        $types = ['potency'];

        if ($settings['include_organon'] ?? true) {
            $types[] = 'organon';
        }

        if ($settings['include_philosophy'] ?? true) {
            $types[] = 'philosophy';
        }

        if ($types === []) {
            return [];
        }

        $queryVector = $this->embedding->toPgVector(
            $this->embedding->embed($queryText)
        );

        $chunks = KnowledgeChunk::query()
            ->with('knowledgeSource')
            ->select('knowledge_chunks.*')
            ->selectRaw('embedding <=> ?::vector as distance', [$queryVector])
            ->whereIn('source_type', $types)
            ->whereNotNull('embedding')
            ->orderByRaw('embedding <=> ?::vector', [$queryVector])
            ->limit(14)
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

    private function emptyObjectWhenBlank(array $value): array|object
    {
        if ($value === []) {
            return new \stdClass;
        }

        return $value;
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item, $key) => is_string($key)
                    ? $key.': '.$this->stringifyValue($item)
                    : $this->stringifyValue($item))
                ->join('; ');
        }

        return (string) $value;
    }
}
