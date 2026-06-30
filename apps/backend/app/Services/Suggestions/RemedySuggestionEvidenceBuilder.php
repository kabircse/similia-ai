<?php

namespace App\Services\Suggestions;

use App\Models\KnowledgeChunk;
use App\Models\MateriaMedicaChunk;
use App\Models\PatientVisit;
use App\Models\Remedy;
use App\Models\RepertorizationRun;
use App\Services\Knowledge\SimpleTextEmbedding;

class RemedySuggestionEvidenceBuilder
{
    public function __construct(
        private readonly SimpleTextEmbedding $embedder
    ) {}

    public function build(
        PatientVisit $visit,
        ?int $repertorizationRunId = null,
        ?string $method = null,
        int $limit = 3,
        array $settings = []
    ): array {
        $visit->load([
            'patient',
            'caseRubrics.rubric.remedies',
            'caseRubrics.rubric',
        ]);

        $run = $this->resolveRun($visit, $repertorizationRunId, $method);

        if (! $run) {
            throw new \RuntimeException('Run repertorization first before generating remedy suggestions.');
        }

        $run->load([
            'results' => fn ($query) => $query->orderBy('rank')->limit($limit),
        ]);

        $results = $run->results;

        if ($results->isEmpty()) {
            throw new \RuntimeException('No repertorization results found.');
        }

        $caseSummary = $this->caseSummary($visit);
        $selectedRubrics = $this->selectedRubrics($visit);
        $queryText = trim($caseSummary."\n\nSelected rubrics:\n".collect($selectedRubrics)
            ->pluck('rubric_path')
            ->join("\n"));

        $queryVector = $this->embedder->toPgVector(
            $this->embedder->embed($queryText)
        );

        $candidates = [];
        $allMateriaChunks = [];

        foreach ($results as $result) {
            $remedy = Remedy::query()
                ->where('code', $result->remedy_code)
                ->orWhere('name', $result->remedy_name)
                ->first();

            $materiaChunks = $this->materiaMedicaChunks($result, $queryVector, $remedy?->id);

            $candidates[] = [
                'remedy_id' => $remedy?->id,
                'remedy_code' => $result->remedy_code,
                'remedy_name' => $result->remedy_name,
                'rank' => $result->rank,
                'total_score' => $result->total_score,
                'rubric_coverage' => $result->rubric_coverage,
                'essential_coverage' => $result->essential_coverage,
                'supporting_rubrics' => $result->supporting_rubrics ?? [],
                'missing_important_rubrics' => $result->missing_important_rubrics ?? [],
                'repertory_evidence' => $this->repertoryEvidence($visit, $result),
                'materia_medica_chunks' => $materiaChunks,
            ];

            $allMateriaChunks = array_merge($allMateriaChunks, $materiaChunks);
        }

        $knowledge = $this->knowledgeChunks($queryVector, $settings);

        return [
            'repertorization_run' => [
                'id' => $run->id,
                'method' => $run->method,
                'total_rubrics' => $run->total_rubrics,
                'essential_rubrics_count' => $run->essential_rubrics_count,
            ],
            'case_snapshot' => [
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->id,
                'chief_complaint' => $visit->chief_complaint,
                'raw_case_text' => $visit->raw_case_text,
                'case_sections' => $visit->case_sections ?? [],
                'missing_questions' => $visit->missing_questions ?? [],
                'red_flags' => $visit->red_flags ?? [],
                'case_summary' => $caseSummary,
            ],
            'selected_rubrics' => $selectedRubrics,
            'candidates' => $candidates,
            'knowledge_chunks' => $knowledge,
            'retrieved_sources' => [
                'materia_medica_chunks_count' => count($allMateriaChunks),
                'knowledge_chunks_count' => count($knowledge),
                'knowledge_types' => collect($knowledge)->pluck('source_type')->unique()->values()->all(),
            ],
        ];
    }

    private function resolveRun(
        PatientVisit $visit,
        ?int $runId,
        ?string $method
    ): ?RepertorizationRun {
        if ($runId) {
            return RepertorizationRun::query()
                ->where('patient_visit_id', $visit->id)
                ->where('id', $runId)
                ->first();
        }

        return RepertorizationRun::query()
            ->where('patient_visit_id', $visit->id)
            ->when($method, fn ($query) => $query->where('method', $method))
            ->latest()
            ->first();
    }

    private function caseSummary(PatientVisit $visit): string
    {
        $sections = collect($visit->case_sections ?? [])
            ->filter()
            ->map(function ($value, $key) {
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }

                return str_replace('_', ' ', $key).': '.$value;
            })
            ->join("\n");

        $redFlags = collect($visit->red_flags ?? [])->filter()->join('; ');

        return trim(implode("\n\n", array_filter([
            $visit->chief_complaint ? 'Chief complaint: '.$visit->chief_complaint : null,
            $visit->raw_case_text ? 'Raw case: '.$visit->raw_case_text : null,
            $sections ?: null,
            $redFlags ? 'Red flags: '.$redFlags : null,
        ])));
    }

    private function selectedRubrics(PatientVisit $visit): array
    {
        return $visit->caseRubrics
            ->map(fn ($caseRubric) => [
                'case_rubric_id' => $caseRubric->id,
                'rubric_id' => $caseRubric->repertory_rubric_id,
                'rubric_path' => $caseRubric->rubric?->rubric_path,
                'symptom_type' => $caseRubric->symptom_type,
                'importance' => $caseRubric->importance,
                'weight' => $caseRubric->weight,
                'is_essential' => $caseRubric->is_essential,
                'note' => $caseRubric->note,
            ])
            ->filter(fn ($item) => $item['rubric_path'])
            ->values()
            ->all();
    }

    private function materiaMedicaChunks($result, string $queryVector, ?int $remedyId): array
    {
        $chunks = MateriaMedicaChunk::query()
            ->with('materiaMedicaSource')
            ->select('materia_medica_chunks.*')
            ->selectRaw('embedding <=> ?::vector as distance', [$queryVector])
            ->where(function ($query) use ($result, $remedyId): void {
                $query->where('remedy_code', $result->remedy_code);

                if ($remedyId) {
                    $query->orWhere('remedy_id', $remedyId);
                }
            })
            ->whereNotNull('embedding')
            ->orderByRaw('embedding <=> ?::vector', [$queryVector])
            ->limit(5)
            ->get();

        return $chunks->map(fn ($chunk) => [
            'id' => $chunk->id,
            'source' => $chunk->source,
            'source_title' => $chunk->source_title,
            'author' => $chunk->metadata['author'] ?? null,
            'edition' => $chunk->metadata['edition'] ?? null,
            'remedy_code' => $chunk->remedy_code,
            'remedy_name' => $chunk->remedy_name,
            'section' => $chunk->section,
            'content' => $chunk->content,
            'distance' => isset($chunk->distance) ? (float) $chunk->distance : null,
        ])->values()->all();
    }

    private function repertoryEvidence(PatientVisit $visit, $result): array
    {
        return [
            'total_score' => $result->total_score,
            'rubric_coverage' => $result->rubric_coverage,
            'essential_coverage' => $result->essential_coverage,
            'supporting_rubrics' => $result->supporting_rubrics ?? [],
            'missing_important_rubrics' => $result->missing_important_rubrics ?? [],
            'selected_rubrics_count' => $visit->caseRubrics->count(),
        ];
    }

    private function knowledgeChunks(string $queryVector, array $settings): array
    {
        $types = [];

        if ($settings['include_potency'] ?? true) {
            $types[] = 'potency';
        }

        if ($settings['include_relationship'] ?? true) {
            $types[] = 'relationship';
        }

        if ($settings['include_medical_safety'] ?? true) {
            $types[] = 'medical';
        }

        if ($settings['include_organon'] ?? true) {
            $types[] = 'organon';
            $types[] = 'philosophy';
        }

        if ($types === []) {
            return [];
        }

        $chunks = KnowledgeChunk::query()
            ->with('knowledgeSource')
            ->select('knowledge_chunks.*')
            ->selectRaw('embedding <=> ?::vector as distance', [$queryVector])
            ->whereIn('source_type', $types)
            ->whereNotNull('embedding')
            ->orderByRaw('embedding <=> ?::vector', [$queryVector])
            ->limit(12)
            ->get();

        return $chunks->map(fn ($chunk) => [
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
}
