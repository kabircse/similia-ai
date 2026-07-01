<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnswerCaseQuestionRequest;
use App\Http\Requests\StartCaseQuestionSessionRequest;
use App\Http\Resources\CaseQuestionSessionResource;
use App\Models\CaseQuestionMessage;
use App\Models\CaseQuestionSession;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Services\Audit\AuditLogger;
use App\Services\CaseTaking\MissingQuestionConversationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use RuntimeException;

class CaseQuestionConversationController extends Controller
{
    public function index(Request $request, Patient $patient, PatientVisit $visit)
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $sessions = CaseQuestionSession::query()
            ->with(['messages' => fn ($query) => $query->orderBy('created_at')])
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return CaseQuestionSessionResource::collection($sessions);
    }

    public function show(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        CaseQuestionSession $questionSession
    ): CaseQuestionSessionResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_unless($questionSession->patient_visit_id === $visit->id, 404);

        return new CaseQuestionSessionResource(
            $questionSession->load(['messages' => fn ($query) => $query->orderBy('created_at')])
        );
    }

    public function start(
        StartCaseQuestionSessionRequest $request,
        Patient $patient,
        PatientVisit $visit,
        MissingQuestionConversationService $service,
        AuditLogger $auditLogger
    ): CaseQuestionSessionResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        try {
            $session = $service->start(
                patient: $patient,
                visit: $visit,
                doctor: $request->user(),
                language: $request->validated('language') ?? 'bn-BD',
                responseLanguage: $request->validated('response_language') ?? 'auto',
                mode: $request->validated('mode') ?? 'ai_missing_questions',
                maxQuestions: (int) ($request->validated('max_questions') ?? 10),
                replaceActiveSession: $request->boolean('replace_active_session')
            );
        } catch (ConnectionException) {
            abort(502, 'AI service is not reachable. Please make sure FastAPI is running on port 8001.');
        } catch (RuntimeException $exception) {
            abort(502, $exception->getMessage());
        }

        $auditLogger->log(
            request: $request,
            category: 'case_taking',
            action: 'started_missing_question_conversation',
            title: 'Missing-question conversation started',
            description: "Started AI missing-question conversation with {$session->total_questions} questions.",
            patient: $patient,
            visit: $visit,
            entity: $session,
            metadata: [
                'language' => $session->language,
                'mode' => $session->mode,
                'total_questions' => $session->total_questions,
            ]
        );

        return new CaseQuestionSessionResource($session);
    }

    public function answer(
        AnswerCaseQuestionRequest $request,
        Patient $patient,
        PatientVisit $visit,
        CaseQuestionSession $questionSession,
        MissingQuestionConversationService $service,
        AuditLogger $auditLogger
    ): CaseQuestionSessionResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_unless($questionSession->patient_visit_id === $visit->id, 404);

        $questionMessage = CaseQuestionMessage::query()
            ->where('case_question_session_id', $questionSession->id)
            ->findOrFail($request->validated('question_message_id'));

        try {
            $session = $service->answer(
                session: $questionSession,
                questionMessage: $questionMessage,
                doctor: $request->user(),
                answerText: $request->validated('answer_text'),
                mergeToCaseText: $request->boolean('merge_to_case_text', true),
                applyToCaseSections: $request->boolean('apply_to_case_sections', true),
                responseLanguage: $request->validated('response_language') ?? null
            );
        } catch (ConnectionException) {
            abort(502, 'AI service is not reachable. Please make sure FastAPI is running on port 8001.');
        } catch (RuntimeException $exception) {
            abort(502, $exception->getMessage());
        }

        $auditLogger->log(
            request: $request,
            category: 'case_taking',
            action: 'answered_missing_question',
            title: 'Missing question answered',
            description: $questionMessage->content,
            patient: $patient,
            visit: $visit,
            entity: $questionMessage,
            metadata: [
                'question_message_id' => $questionMessage->id,
                'session_id' => $questionSession->id,
                'category' => $questionMessage->category,
            ]
        );

        return new CaseQuestionSessionResource($session);
    }

    public function complete(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        CaseQuestionSession $questionSession,
        AuditLogger $auditLogger
    ): CaseQuestionSessionResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_unless($questionSession->patient_visit_id === $visit->id, 404);

        $questionSession->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $auditLogger->log(
            request: $request,
            category: 'case_taking',
            action: 'completed_missing_question_conversation',
            title: 'Missing-question conversation completed',
            description: 'Doctor manually completed the missing-question session.',
            patient: $patient,
            visit: $visit,
            entity: $questionSession
        );

        return new CaseQuestionSessionResource(
            $questionSession->fresh()->load(['messages' => fn ($query) => $query->orderBy('created_at')])
        );
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
