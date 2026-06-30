<?php

namespace App\Services\CaseTaking;

use App\Models\CaseQuestionMessage;
use App\Models\CaseQuestionSession;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MissingQuestionConversationService
{
    public function start(
        Patient $patient,
        PatientVisit $visit,
        User $doctor,
        string $language = 'bn-BD',
        string $mode = 'ai_missing_questions',
        int $maxQuestions = 10,
        bool $replaceActiveSession = false
    ): CaseQuestionSession {
        $questions = $this->fetchQuestionsFromAi(
            visit: $visit,
            language: $language,
            maxQuestions: $maxQuestions
        );

        if ($questions === []) {
            throw new RuntimeException('No missing questions were generated.');
        }

        return DB::transaction(function () use (
            $patient,
            $visit,
            $doctor,
            $language,
            $mode,
            $maxQuestions,
            $replaceActiveSession,
            $questions
        ): CaseQuestionSession {
            if ($replaceActiveSession) {
                CaseQuestionSession::query()
                    ->where('patient_visit_id', $visit->id)
                    ->where('doctor_id', $doctor->id)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'cancelled',
                        'completed_at' => now(),
                    ]);
            }

            $session = CaseQuestionSession::create([
                'patient_id' => $patient->id,
                'patient_visit_id' => $visit->id,
                'doctor_id' => $doctor->id,
                'status' => 'active',
                'language' => $language,
                'mode' => $mode,
                'total_questions' => count($questions),
                'answered_questions' => 0,
                'case_snapshot' => [
                    'chief_complaint' => $visit->chief_complaint,
                    'raw_case_text' => $visit->raw_case_text,
                    'case_sections' => $visit->case_sections ?? [],
                    'missing_questions' => $visit->missing_questions ?? [],
                    'red_flags' => $visit->red_flags ?? [],
                ],
                'settings' => [
                    'max_questions' => $maxQuestions,
                ],
                'started_at' => now(),
            ]);

            foreach ($questions as $question) {
                CaseQuestionMessage::create([
                    'case_question_session_id' => $session->id,
                    'patient_id' => $patient->id,
                    'patient_visit_id' => $visit->id,
                    'doctor_id' => $doctor->id,
                    'role' => 'assistant',
                    'message_type' => 'question',
                    'status' => 'pending',
                    'question_key' => $question['question_key'] ?? null,
                    'category' => $question['category'] ?? null,
                    'importance' => $question['importance'] ?? 'normal',
                    'content' => $question['question'] ?? $question['content'] ?? '',
                    'metadata' => [
                        'source' => 'ai_missing_question_conversation',
                    ],
                ]);
            }

            return $session->fresh()->load([
                'messages' => fn ($query) => $query->orderBy('created_at'),
            ]);
        });
    }

    public function answer(
        CaseQuestionSession $session,
        CaseQuestionMessage $questionMessage,
        User $doctor,
        string $answerText,
        bool $mergeToCaseText = true,
        bool $applyToCaseSections = true
    ): CaseQuestionSession {
        abort_unless($questionMessage->case_question_session_id === $session->id, 404);
        abort_unless($questionMessage->role === 'assistant', 422);
        abort_unless($questionMessage->message_type === 'question', 422);
        abort_unless($questionMessage->status === 'pending', 422, 'Question already answered.');

        $visit = $session->visit;

        if (! $visit) {
            throw new RuntimeException('Visit is no longer available.');
        }

        $aiUpdate = $this->applyAnswerWithAi(
            visit: $visit,
            questionMessage: $questionMessage,
            answerText: $answerText
        );

        return DB::transaction(function () use (
            $session,
            $questionMessage,
            $doctor,
            $answerText,
            $mergeToCaseText,
            $applyToCaseSections,
            $visit,
            $aiUpdate
        ): CaseQuestionSession {
            CaseQuestionMessage::create([
                'case_question_session_id' => $session->id,
                'patient_id' => $session->patient_id,
                'patient_visit_id' => $session->patient_visit_id,
                'doctor_id' => $doctor->id,
                'parent_message_id' => $questionMessage->id,
                'role' => 'doctor',
                'message_type' => 'answer',
                'status' => 'saved',
                'question_key' => $questionMessage->question_key,
                'category' => $questionMessage->category,
                'importance' => $questionMessage->importance,
                'content' => $answerText,
                'extracted_update' => $aiUpdate,
                'metadata' => [
                    'merge_to_case_text' => $mergeToCaseText,
                    'apply_to_case_sections' => $applyToCaseSections,
                ],
            ]);

            $questionMessage->update([
                'status' => 'answered',
                'answered_at' => now(),
                'extracted_update' => $aiUpdate,
            ]);

            if ($applyToCaseSections) {
                $this->applyCaseSectionUpdates($visit, $aiUpdate['case_section_updates'] ?? []);
            }

            if ($mergeToCaseText) {
                $this->appendQuestionAnswerToRawCase(
                    visit: $visit,
                    question: $questionMessage->content,
                    answer: $answerText,
                    rawNote: $aiUpdate['raw_case_note'] ?? null
                );
            }

            $answeredCount = CaseQuestionMessage::query()
                ->where('case_question_session_id', $session->id)
                ->where('role', 'assistant')
                ->where('message_type', 'question')
                ->where('status', 'answered')
                ->count();

            $pendingCount = CaseQuestionMessage::query()
                ->where('case_question_session_id', $session->id)
                ->where('role', 'assistant')
                ->where('message_type', 'question')
                ->where('status', 'pending')
                ->count();

            $session->update([
                'answered_questions' => $answeredCount,
                'status' => $pendingCount === 0 ? 'completed' : 'active',
                'completed_at' => $pendingCount === 0 ? now() : null,
            ]);

            return $session->fresh()->load([
                'messages' => fn ($query) => $query->orderBy('created_at'),
            ]);
        });
    }

    private function fetchQuestionsFromAi(
        PatientVisit $visit,
        string $language,
        int $maxQuestions
    ): array {
        $response = Http::timeout(config('services.ai_service.timeout'))
            ->acceptJson()
            ->post(rtrim(config('services.ai_service.url'), '/').'/case/missing-question-conversation/start', [
                'language' => $language,
                'max_questions' => $maxQuestions,
                'raw_case_text' => $visit->raw_case_text,
                'chief_complaint' => $visit->chief_complaint,
                'case_sections' => $visit->case_sections ?? [],
                'missing_questions' => $visit->missing_questions ?? [],
                'red_flags' => $visit->red_flags ?? [],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('AI service failed to generate missing questions.');
        }

        $questions = $response->json('questions');

        if (! is_array($questions)) {
            $questions = $response->json('data.questions');
        }

        return is_array($questions) ? $questions : [];
    }

    private function applyAnswerWithAi(
        PatientVisit $visit,
        CaseQuestionMessage $questionMessage,
        string $answerText
    ): array {
        $response = Http::timeout(config('services.ai_service.timeout'))
            ->acceptJson()
            ->post(rtrim(config('services.ai_service.url'), '/').'/case/missing-question-conversation/apply-answer', [
                'question_key' => $questionMessage->question_key,
                'category' => $questionMessage->category,
                'question' => $questionMessage->content,
                'answer' => $answerText,
                'existing_case_sections' => $visit->case_sections ?? [],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('AI service failed to apply answer.');
        }

        $data = $response->json('data') ?? $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('AI service returned an invalid case update.');
        }

        return $data;
    }

    private function applyCaseSectionUpdates(PatientVisit $visit, array $updates): void
    {
        if ($updates === []) {
            return;
        }

        $caseSections = $visit->case_sections ?? [];

        foreach ($updates as $key => $value) {
            $caseSections[$key] = $value;
        }

        $visit->update([
            'case_sections' => $caseSections,
            'case_source' => 'mixed',
        ]);
    }

    private function appendQuestionAnswerToRawCase(
        PatientVisit $visit,
        string $question,
        string $answer,
        ?string $rawNote = null
    ): void {
        $existing = trim((string) $visit->raw_case_text);
        $block = trim(implode("\n", array_filter([
            '[Missing-question conversation - '.now()->format('Y-m-d H:i').']',
            $rawNote ?: "Q: {$question}\nA: {$answer}",
        ])));

        $visit->update([
            'raw_case_text' => trim($existing."\n\n".$block),
            'case_source' => 'mixed',
        ]);
    }
}
