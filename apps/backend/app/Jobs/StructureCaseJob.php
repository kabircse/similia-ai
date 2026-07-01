<?php

namespace App\Jobs;

use App\Models\AiTask;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class StructureCaseJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $aiTaskId
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $task = AiTask::query()
            ->with(['user', 'patient', 'visit'])
            ->findOrFail($this->aiTaskId);

        $visit = $task->visit;

        $task->markRunning('Sending case text to AI service.');

        try {
            if (! $visit) {
                throw new RuntimeException('Visit is no longer available.');
            }

            $task->update(['progress' => 40]);

            $existingSections = $visit->case_sections ?? [];

            if ($existingSections === []) {
                $existingSections = new \stdClass;
            }

            $response = Http::timeout(config('services.ai_service.timeout'))
                ->acceptJson()
                ->post(rtrim(config('services.ai_service.url'), '/').'/case/structure', [
                    'raw_text' => $visit->raw_case_text ?? '',
                    'chief_complaint' => $visit->chief_complaint,
                    'existing_case_sections' => $existingSections,
                ]);

            if ($response->failed()) {
                throw new RuntimeException('AI service failed with status '.$response->status());
            }

            $data = $response->json('data') ?? $response->json();

            if (! is_array($data)) {
                throw new RuntimeException('AI service returned an invalid response.');
            }

            $task->update(['progress' => 75]);

            $visit->update([
                'case_source' => 'mixed',
                'chief_complaint' => $data['chief_complaint'] ?? $visit->chief_complaint,
                'case_sections' => $this->mergeCaseSections(
                    $visit->case_sections ?? [],
                    $data['case_sections'] ?? []
                ),
                'missing_questions' => $data['missing_questions'] ?? [],
                'red_flags' => $data['red_flags'] ?? [],
            ]);

            $visit = $visit->fresh();

            $result = [
                'visit_id' => $visit->id,
                'case_sections' => $visit->case_sections,
                'missing_questions' => $visit->missing_questions,
                'red_flags' => $visit->red_flags,
            ];

            $task->markCompleted($result, 'AI case structuring completed.');

            $notificationService->create(
                user: $task->user,
                title: 'AI case structuring completed',
                message: ($task->patient?->name ?? 'Patient').' visit is now structured.',
                type: 'success',
                category: 'ai',
                patient: $task->patient,
                visit: $visit,
                aiTask: $task,
                actionUrl: "/patients/{$task->patient_id}/visits/{$task->patient_visit_id}",
                metadata: [
                    'missing_questions_count' => count($visit->missing_questions ?? []),
                    'red_flags_count' => count($visit->red_flags ?? []),
                ]
            );
        } catch (Throwable $exception) {
            $task->markFailed($exception->getMessage());

            $notificationService->create(
                user: $task->user,
                title: 'AI case structuring failed',
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

    private function mergeCaseSections(array $existing, array $incoming): array
    {
        $merged = $existing;

        foreach ($incoming as $key => $value) {
            $value = is_string($value) ? trim($value) : $value;

            if ($value === '' || $value === null) {
                continue;
            }

            if (empty($merged[$key])) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
