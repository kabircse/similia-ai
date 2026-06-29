<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompareMateriaMedicaRequest;
use App\Models\MateriaMedicaChunk;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\RepertorizationRun;
use App\Services\Knowledge\SimpleTextEmbedding;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MateriaMedicaComparisonController extends Controller
{
    public function compare(
        CompareMateriaMedicaRequest $request,
        Patient $patient,
        PatientVisit $visit,
        SimpleTextEmbedding $embedder
    ) {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $run = $this->resolveRun($request, $visit);

        $limit = (int) ($request->validated('limit') ?? 3);
        $limit = max(1, min($limit, 5));

        $results = $run->results()
            ->orderBy('rank')
            ->limit($limit)
            ->get();

        if ($results->isEmpty()) {
            abort(422, 'No repertorization results found for materia medica comparison.');
        }

        $caseSummary = $this->buildCaseSummary($visit);

        $queryVector = $embedder->toPgVector(
            $embedder->embed($caseSummary)
        );

        $chunks = collect();

        foreach ($results as $result) {
            $retrieved = MateriaMedicaChunk::query()
                ->where('remedy_code', $result->remedy_code)
                ->whereNotNull('embedding')
                ->select('*')
                ->selectRaw('embedding <=> ?::vector as distance', [$queryVector])
                ->orderByRaw('embedding <=> ?::vector', [$queryVector])
                ->limit(4)
                ->get();

            $chunks = $chunks->merge($retrieved);
        }

        $payload = [
            'case_summary' => $caseSummary,
            'candidates' => $results->map(fn ($result) => [
                'remedy_code' => $result->remedy_code,
                'remedy_name' => $result->remedy_name,
                'rank' => $result->rank,
                'total_score' => $result->total_score,
                'rubric_coverage' => $result->rubric_coverage,
                'essential_coverage' => $result->essential_coverage,
            ])->values()->all(),
            'chunks' => $chunks->map(fn ($chunk) => [
                'remedy_code' => $chunk->remedy_code,
                'remedy_name' => $chunk->remedy_name,
                'section' => $chunk->section,
                'content' => $chunk->content,
                'source_title' => $chunk->source_title,
                'distance' => isset($chunk->distance) ? (float) $chunk->distance : null,
            ])->values()->all(),
        ];

        try {
            $response = Http::timeout(config('services.ai_service.timeout'))
                ->acceptJson()
                ->post(rtrim(config('services.ai_service.url'), '/').'/materia-medica/compare', $payload);
        } catch (ConnectionException) {
            abort(502, 'AI service is not reachable. Please make sure FastAPI is running on port 8001.');
        }

        if ($response->failed()) {
            abort(502, 'AI service failed to compare remedies.');
        }

        return response()->json([
            'data' => $response->json('data'),
            'meta' => [
                'repertorization_run_id' => $run->id,
                'method' => $run->method,
                'retrieved_chunks' => $chunks->count(),
            ],
        ]);
    }

    private function resolveRun(CompareMateriaMedicaRequest $request, PatientVisit $visit): RepertorizationRun
    {
        $runId = $request->validated('repertorization_run_id') ?? null;
        $method = $request->validated('method') ?? null;

        $query = RepertorizationRun::query()
            ->where('patient_visit_id', $visit->id);

        if ($runId) {
            $query->where('id', $runId);
        }

        if ($method) {
            $query->where('method', $method);
        }

        $run = $query->latest()->first();

        if (! $run) {
            abort(422, 'Please run repertorization before materia medica comparison.');
        }

        return $run;
    }

    private function buildCaseSummary(PatientVisit $visit): string
    {
        $sections = collect($visit->case_sections ?? [])
            ->filter()
            ->map(fn ($value, $key) => str_replace('_', ' ', $key).': '.$value)
            ->implode("\n");

        $rubrics = $visit->caseRubrics()
            ->with('rubric')
            ->get()
            ->map(fn ($caseRubric) => $caseRubric->rubric?->rubric_path)
            ->filter()
            ->implode("\n");

        return trim(implode("\n\n", array_filter([
            $visit->chief_complaint ? 'Chief complaint: '.$visit->chief_complaint : null,
            $visit->raw_case_text ? 'Raw case: '.$visit->raw_case_text : null,
            $sections ?: null,
            $rubrics ? 'Selected rubrics: '.$rubrics : null,
        ])));
    }

    private function ensureCanAccessVisit(CompareMateriaMedicaRequest $request, Patient $patient, PatientVisit $visit): void
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
