<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveVoiceTranscriptRequest;
use App\Http\Resources\VoiceTranscriptResource;
use App\Jobs\StructureCaseJob;
use App\Models\AiTask;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\VoiceTranscript;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoiceTranscriptController extends Controller
{
    public function index(Request $request, Patient $patient, PatientVisit $visit)
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $transcripts = VoiceTranscript::query()
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return VoiceTranscriptResource::collection($transcripts);
    }

    public function store(
        SaveVoiceTranscriptRequest $request,
        Patient $patient,
        PatientVisit $visit,
        AuditLogger $auditLogger
    ): VoiceTranscriptResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $data = $request->validated();
        $mergeToCaseText = $request->boolean('merge_to_case_text', true);
        $mergeMode = $data['merge_mode'] ?? 'append';

        [$transcript, $task] = DB::transaction(function () use (
            $request,
            $patient,
            $visit,
            $auditLogger,
            $data,
            $mergeToCaseText,
            $mergeMode
        ) {
            $transcript = VoiceTranscript::create([
                'patient_id' => $patient->id,
                'patient_visit_id' => $visit->id,
                'doctor_id' => $request->user()->id,

                'language' => $data['language'],
                'source' => 'browser_speech_recognition',
                'status' => 'completed',

                'transcript_text' => $data['transcript_text'],
                'segments' => $data['segments'] ?? [],

                'merged_to_case_text' => $mergeToCaseText,
                'merge_mode' => $mergeToCaseText ? $mergeMode : null,

                'started_at' => $data['started_at'] ?? null,
                'completed_at' => $data['completed_at'] ?? now(),

                'metadata' => [
                    'characters' => mb_strlen($data['transcript_text']),
                    'words_estimate' => str_word_count(strip_tags($data['transcript_text'])),
                ],
            ]);

            if ($mergeToCaseText) {
                $this->mergeTranscriptToVisit($visit, $data['transcript_text'], $mergeMode);
                $visit->refresh();
            }

            $task = $mergeToCaseText
                ? $this->createStructureCaseTask($request, $patient, $visit, $transcript)
                : null;

            $auditLogger->log(
                request: $request,
                category: 'voice',
                action: 'saved_transcript',
                title: 'Voice transcript saved',
                description: $mergeToCaseText
                    ? "Voice transcript saved and merged to case text using {$mergeMode} mode."
                    : 'Voice transcript saved.',
                patient: $patient,
                visit: $visit,
                entity: $transcript,
                metadata: [
                    'language' => $transcript->language,
                    'merged_to_case_text' => $mergeToCaseText,
                    'merge_mode' => $mergeToCaseText ? $mergeMode : null,
                    'characters' => mb_strlen($transcript->transcript_text),
                    'queued_structure_case_task_id' => $task?->id,
                ]
            );

            return [$transcript, $task];
        });

        if ($task) {
            StructureCaseJob::dispatch($task->id);
        }

        return (new VoiceTranscriptResource($transcript->fresh()))
            ->additional([
                'meta' => [
                    'queued_ai_task_id' => $task?->id,
                ],
            ]);
    }

    public function show(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        VoiceTranscript $voiceTranscript
    ): VoiceTranscriptResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_unless($voiceTranscript->patient_visit_id === $visit->id, 404);

        return new VoiceTranscriptResource($voiceTranscript);
    }

    private function mergeTranscriptToVisit(
        PatientVisit $visit,
        string $transcript,
        string $mergeMode
    ): void {
        $existing = trim((string) $visit->raw_case_text);
        $transcript = trim($transcript);

        $voiceBlock = trim(
            '[Voice transcript - '.now()->format('Y-m-d H:i')."]\n".$transcript
        );

        $newText = match ($mergeMode) {
            'replace' => $voiceBlock,
            'prepend' => trim($voiceBlock."\n\n".$existing),
            default => trim($existing."\n\n".$voiceBlock),
        };

        $visit->update([
            'raw_case_text' => $newText,
            'case_source' => 'mixed',
        ]);
    }

    private function createStructureCaseTask(
        SaveVoiceTranscriptRequest $request,
        Patient $patient,
        PatientVisit $visit,
        VoiceTranscript $transcript
    ): AiTask {
        return AiTask::create([
            'user_id' => $request->user()->id,
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
            'type' => 'structure_case',
            'status' => 'queued',
            'title' => 'AI case structuring',
            'message' => 'Voice transcript case structuring has been queued.',
            'progress' => 0,
            'payload' => [
                'visit_id' => $visit->id,
                'voice_transcript_id' => $transcript->id,
                'chief_complaint' => $visit->chief_complaint,
                'source' => 'voice_transcript',
            ],
        ]);
    }

    private function ensureCanAccessVisit(Request $request, Patient $patient, PatientVisit $visit): void
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
