<?php

namespace App\Jobs;

use App\Models\AiTask;
use App\Models\MateriaMedicaChunk;
use App\Models\PatientVisit;
use App\Models\RepertorizationRun;
use App\Services\Knowledge\SimpleTextEmbedding;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class CompareMateriaMedicaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public int $aiTaskId
    ) {}

    public function handle(
        SimpleTextEmbedding $embedder,
        NotificationService $notificationService
    ): void {
        $task = AiTask::query()
            ->with(['user', 'patient', 'visit.caseRubrics.rubric'])
            ->findOrFail($this->aiTaskId);

        $visit = $task->visit;
        $payload = $task->payload ?? [];

        $task->markRunning('Preparing materia medica comparison.');

        try {
            if (! $visit) {
                throw new RuntimeException('Visit is no longer available.');
            }

            $run = $this->resolveRun($visit->id, $payload);

            if (! $run) {
                throw new RuntimeException('Please run repertorization before materia medica comparison.');
            }

            $task->update(['progress' => 30]);

            $results = $run->results()
                ->orderBy('rank')
                ->limit((int) ($payload['limit'] ?? 3))
                ->get();

            if ($results->isEmpty()) {
                throw new RuntimeException('No repertorization results found.');
            }

            $caseSummary = $this->buildCaseSummary($visit);
            $queryVector = $embedder->toPgVector($embedder->embed($caseSummary));

            $task->update(['progress' => 50]);

            $chunks = collect();

            foreach ($results as $result) {
                $retrieved = MateriaMedicaChunk::query()
                    ->where(function ($query) use ($result): void {
                        $query->where('remedy_code', $result->remedy_code);

                        if ($result->remedy_id ?? null) {
                            $query->orWhere('remedy_id', $result->remedy_id);
                        }
                    })
                    ->whereNotNull('embedding')
                    ->select('*')
                    ->selectRaw('embedding <=> ?::vector as distance', [$queryVector])
                    ->orderByRaw('embedding <=> ?::vector', [$queryVector])
                    ->limit(4)
                    ->get();

                $chunks = $chunks->merge($retrieved);
            }

            $task->update(['progress' => 70]);

            $response = Http::timeout(config('services.ai_service.timeout'))
                ->acceptJson()
                ->post(rtrim(config('services.ai_service.url'), '/').'/materia-medica/compare', [
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
                        'source' => $chunk->source,
                        'source_title' => $chunk->source_title,
                        'distance' => isset($chunk->distance) ? (float) $chunk->distance : null,
                    ])->values()->all(),
                ]);

            if ($response->failed()) {
                throw new RuntimeException('AI service failed with status '.$response->status());
            }

            $comparison = $response->json('data') ?? $response->json();

            if (! is_array($comparison)) {
                throw new RuntimeException('AI service returned an invalid response.');
            }

            $task->markCompleted([
                'repertorization_run_id' => $run->id,
                'method' => $run->method,
                'comparison' => $comparison,
                'retrieved_chunks' => $chunks->count(),
            ], 'Materia medica comparison completed.');

            $notificationService->create(
                user: $task->user,
                title: 'Materia medica comparison completed',
                message: 'Comparison is ready for '.($task->patient?->name ?? 'patient').'.',
                type: 'success',
                category: 'ai',
                patient: $task->patient,
                visit: $visit,
                aiTask: $task,
                actionUrl: "/patients/{$task->patient_id}/visits/{$task->patient_visit_id}",
                metadata: [
                    'method' => $run->method,
                    'repertorization_run_id' => $run->id,
                    'remedies_count' => $results->count(),
                    'chunks_count' => $chunks->count(),
                ]
            );
        } catch (Throwable $exception) {
            $task->markFailed($exception->getMessage());

            $notificationService->create(
                user: $task->user,
                title: 'Materia medica comparison failed',
                message: $exception->getMessage(),
                type: 'error',
                category: 'ai',
                patient: $task->patient,
                visit: $visit,
                aiTask: $task,
                actionUrl: "/patients/{$task->patient_id}/visits/{$task->patient_visit_id}"
            );

            throw $exception;
        }
    }

    private function resolveRun(int $visitId, array $payload): ?RepertorizationRun
    {
        $query = RepertorizationRun::query()
            ->where('patient_visit_id', $visitId);

        if (! empty($payload['repertorization_run_id'])) {
            $query->where('id', $payload['repertorization_run_id']);
        }

        if (! empty($payload['method'])) {
            $query->where('method', $payload['method']);
        }

        return $query->latest()->first();
    }

    private function buildCaseSummary(PatientVisit $visit): string
    {
        $sections = collect($visit->case_sections ?? [])
            ->filter()
            ->map(function ($value, $key) {
                $value = is_scalar($value) ? $value : json_encode($value);

                return str_replace('_', ' ', $key).': '.$value;
            })
            ->implode("\n");

        $rubrics = $visit->caseRubrics
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
}
