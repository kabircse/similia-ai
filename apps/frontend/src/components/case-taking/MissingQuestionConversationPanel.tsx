import { useEffect, useMemo, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  CheckCircle2,
  MessageCircleQuestion,
  Mic,
  MicOff,
  Send,
} from "lucide-react";
import {
  answerCaseQuestion,
  completeCaseQuestionSession,
  getCaseQuestionSessions,
  startCaseQuestionSession,
  type AiResponseLanguage,
  type CaseQuestionMessage,
  type CaseQuestionSession,
} from "../../lib/api";

type MissingQuestionConversationPanelProps = {
  patientId: string | number;
  visitId: string | number;
};

function apiErrorMessage(error: unknown, fallback: string) {
  if (
    error &&
    typeof error === "object" &&
    "response" in error &&
    error.response &&
    typeof error.response === "object" &&
    "data" in error.response &&
    error.response.data &&
    typeof error.response.data === "object" &&
    "message" in error.response.data &&
    typeof error.response.data.message === "string"
  ) {
    return error.response.data.message;
  }

  return fallback;
}

export function MissingQuestionConversationPanel({
  patientId,
  visitId,
}: MissingQuestionConversationPanelProps) {
  const queryClient = useQueryClient();
  const recognitionRef = useRef<SpeechRecognition | null>(null);
  const interimAnswerRef = useRef("");

  const [language, setLanguage] = useState("bn-BD");
  const [responseLanguage, setResponseLanguage] =
    useState<AiResponseLanguage>("auto");
  const [maxQuestions, setMaxQuestions] = useState(8);
  const [answerText, setAnswerText] = useState("");
  const [isDictating, setIsDictating] = useState(false);
  const [interimAnswer, setInterimAnswer] = useState("");
  const [dictationError, setDictationError] = useState<string | null>(null);
  const isSpeechSupported = Boolean(
    window.SpeechRecognition ?? window.webkitSpeechRecognition
  );

  const sessionsQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "question-sessions"],
    queryFn: () => getCaseQuestionSessions(patientId, visitId),
  });

  const latestSession = sessionsQuery.data?.data?.[0];

  const activeSession = useMemo(() => {
    if (latestSession?.status === "active") {
      return latestSession;
    }

    return latestSession ?? null;
  }, [latestSession]);

  const currentQuestion = useMemo(() => {
    return activeSession?.messages.find(
      (message) =>
        message.role === "assistant" &&
        message.message_type === "question" &&
        message.status === "pending"
    );
  }, [activeSession]);

  const startMutation = useMutation({
    mutationFn: () =>
      startCaseQuestionSession(patientId, visitId, {
        language,
        response_language: responseLanguage,
        max_questions: maxQuestions,
        replace_active_session: true,
      }),
    onSuccess: async () => {
      setAnswerText("");
      resetDictation();
      await invalidateAll(queryClient, patientId, visitId);
    },
  });

  const answerMutation = useMutation({
    mutationFn: () => {
      if (!activeSession || !currentQuestion) {
        throw new Error("No active question.");
      }

      return answerCaseQuestion(patientId, visitId, activeSession.id, {
        question_message_id: currentQuestion.id,
        answer_text: [answerText, interimAnswer].filter(Boolean).join(" ").trim(),
        merge_to_case_text: true,
        apply_to_case_sections: true,
        response_language: responseLanguage,
      });
    },
    onSuccess: async () => {
      setAnswerText("");
      resetDictation();
      await invalidateAll(queryClient, patientId, visitId);
    },
  });

  const completeMutation = useMutation({
    mutationFn: () => {
      if (!activeSession) {
        throw new Error("No active session.");
      }

      return completeCaseQuestionSession(patientId, visitId, activeSession.id);
    },
    onSuccess: async () => {
      await invalidateAll(queryClient, patientId, visitId);
    },
  });

  useEffect(() => {
    const Recognition =
      window.SpeechRecognition ?? window.webkitSpeechRecognition;

    if (!Recognition) {
      return;
    }

    const recognition = new Recognition();

    recognition.continuous = true;
    recognition.interimResults = true;
    recognition.maxAlternatives = 1;
    recognition.lang = language;

    recognition.onstart = () => {
      setIsDictating(true);
      setDictationError(null);
    };

    recognition.onend = () => {
      setIsDictating(false);

      if (interimAnswerRef.current) {
        setAnswerText((current) =>
          `${current} ${interimAnswerRef.current}`.trim()
        );
        interimAnswerRef.current = "";
        setInterimAnswer("");
      }
    };

    recognition.onerror = (event) => {
      setDictationError(event.message || event.error || "Voice dictation failed.");
      setIsDictating(false);
    };

    recognition.onresult = (event) => {
      let interim = "";
      let final = "";

      for (let index = event.resultIndex; index < event.results.length; index += 1) {
        const result = event.results[index];
        const text = result[0].transcript.trim();

        if (!text) {
          continue;
        }

        if (result.isFinal) {
          final += `${text} `;
        } else {
          interim += `${text} `;
        }
      }

      if (final) {
        setAnswerText((current) => `${current} ${final}`.trim());
      }

      const nextInterim = interim.trim();
      interimAnswerRef.current = nextInterim;
      setInterimAnswer(nextInterim);
    };

    recognitionRef.current = recognition;

    return () => {
      recognition.abort();
      recognitionRef.current = null;
    };
  }, [language]);

  function startDictation() {
    if (!recognitionRef.current) {
      return;
    }

    setDictationError(null);

    try {
      recognitionRef.current.lang = language;
      recognitionRef.current.start();
    } catch {
      setDictationError("Voice dictation is already running or unavailable.");
    }
  }

  function stopDictation() {
    recognitionRef.current?.stop();
  }

  function resetDictation() {
    recognitionRef.current?.stop();
    interimAnswerRef.current = "";
    setInterimAnswer("");
    setIsDictating(false);
    setDictationError(null);
  }

  return (
    <section className="panel question-conversation-panel">
      <div className="panel-heading">
        <div>
          <h3>
            <MessageCircleQuestion size={20} /> Missing-Question Conversation
          </h3>
          <p className="panel-subtitle">
            Doctor-controlled follow-up questions for this case.
          </p>
        </div>
      </div>

      <div className="safety-note">
        Case completion only. Remedy, potency, and prescription remain doctor decisions.
      </div>

      <div className="conversation-start-row">
        <label>
          Dictation Language
          <select
            className="method-select"
            value={language}
            onChange={(event) => setLanguage(event.target.value)}
            disabled={startMutation.isPending || isDictating}
          >
            <option value="bn-BD">Bangla</option>
            <option value="en-US">English</option>
          </select>
        </label>

        <label>
          AI Response Language
          <select
            className="method-select"
            value={responseLanguage}
            onChange={(event) =>
              setResponseLanguage(event.target.value as AiResponseLanguage)
            }
          >
            <option value="auto">Auto Detect</option>
            <option value="bn-BD">Bangla</option>
            <option value="en-US">English</option>
            <option value="hi-IN">Hindi</option>
          </select>
        </label>

        <label>
          Questions
          <select
            className="method-select"
            value={maxQuestions}
            onChange={(event) => setMaxQuestions(Number(event.target.value))}
          >
            <option value={5}>5 Questions</option>
            <option value={8}>8 Questions</option>
            <option value={10}>10 Questions</option>
            <option value={15}>15 Questions</option>
          </select>
        </label>

        <button
          className="primary-button inline-button"
          onClick={() => startMutation.mutate()}
          disabled={startMutation.isPending}
        >
          <MessageCircleQuestion size={16} />
          {startMutation.isPending ? "Starting..." : "Start Conversation"}
        </button>
      </div>

      {startMutation.isError && (
        <div className="form-error">
          {apiErrorMessage(
            startMutation.error,
            "Could not start missing-question conversation."
          )}
        </div>
      )}

      {sessionsQuery.isLoading && (
        <p className="empty-state">Loading conversation...</p>
      )}

      {activeSession ? (
        <ConversationView
          session={activeSession}
          currentQuestion={currentQuestion}
          answerText={answerText}
          interimAnswer={interimAnswer}
          setAnswerText={setAnswerText}
          onAnswer={() => answerMutation.mutate()}
          answering={answerMutation.isPending}
          answerError={
            answerMutation.isError
              ? apiErrorMessage(answerMutation.error, "Could not save answer.")
              : null
          }
          onComplete={() => completeMutation.mutate()}
          completing={completeMutation.isPending}
          completeError={
            completeMutation.isError
              ? apiErrorMessage(
                  completeMutation.error,
                  "Could not complete conversation."
                )
              : null
          }
          isSpeechSupported={isSpeechSupported}
          isDictating={isDictating}
          onStartDictation={startDictation}
          onStopDictation={stopDictation}
          dictationError={dictationError}
        />
      ) : (
        <p className="empty-state">
          No missing-question conversation has been started for this visit.
        </p>
      )}
    </section>
  );
}

function ConversationView({
  session,
  currentQuestion,
  answerText,
  interimAnswer,
  setAnswerText,
  onAnswer,
  answering,
  answerError,
  onComplete,
  completing,
  completeError,
  isSpeechSupported,
  isDictating,
  onStartDictation,
  onStopDictation,
  dictationError,
}: {
  session: CaseQuestionSession;
  currentQuestion: CaseQuestionMessage | undefined;
  answerText: string;
  interimAnswer: string;
  setAnswerText: (value: string) => void;
  onAnswer: () => void;
  answering: boolean;
  answerError: string | null;
  onComplete: () => void;
  completing: boolean;
  completeError: string | null;
  isSpeechSupported: boolean;
  isDictating: boolean;
  onStartDictation: () => void;
  onStopDictation: () => void;
  dictationError: string | null;
}) {
  const answerValue = [answerText, interimAnswer].filter(Boolean).join(" ").trim();

  return (
    <div className="conversation-box">
      <div className="conversation-progress">
        <strong>
          {session.answered_questions}/{session.total_questions} answered
        </strong>
        <span>Status: {session.status}</span>
      </div>

      {currentQuestion ? (
        <div className={`question-card ${currentQuestion.importance}`}>
          <p className="eyebrow">
            {currentQuestion.category ?? "question"} · {currentQuestion.importance}
          </p>

          <h4>{currentQuestion.content}</h4>

          <textarea
            rows={4}
            value={answerText}
            onChange={(event) => setAnswerText(event.target.value)}
            placeholder="Write the doctor's answer here..."
          />

          {interimAnswer && (
            <div className="voice-interim-text">
              <strong>Interim</strong>
              <p>{interimAnswer}</p>
            </div>
          )}

          {dictationError && <div className="form-error">{dictationError}</div>}
          {answerError && <div className="form-error">{answerError}</div>}

          <div className="form-actions">
            {isSpeechSupported && (
              !isDictating ? (
                <button
                  className="secondary-button inline-button"
                  onClick={onStartDictation}
                  type="button"
                >
                  <Mic size={16} />
                  Voice Answer
                </button>
              ) : (
                <button
                  className="danger-button inline-button"
                  onClick={onStopDictation}
                  type="button"
                >
                  <MicOff size={16} />
                  Stop
                </button>
              )
            )}

            <button
              className="primary-button inline-button"
              onClick={onAnswer}
              disabled={!answerValue || answering || isDictating}
            >
              <Send size={16} />
              {answering ? "Saving..." : "Save Answer"}
            </button>
          </div>
        </div>
      ) : (
        <div className="success-panel inline-button">
          <CheckCircle2 size={16} /> All pending questions are answered.
        </div>
      )}

      <details className="source-details" open>
        <summary>Conversation History</summary>

        <div className="conversation-history">
          {session.messages.map((message) => (
            <div
              key={message.id}
              className={`conversation-message ${message.role}`}
            >
              <strong>
                {message.role === "assistant" ? "AI Question" : "Doctor Answer"}
              </strong>
              <p>{message.content}</p>
              <small>
                {message.category ? `${message.category} · ` : ""}
                {message.status}
              </small>
            </div>
          ))}
        </div>
      </details>

      {completeError && <div className="form-error">{completeError}</div>}

      {session.status === "active" && (
        <button
          className="secondary-button inline-button"
          onClick={onComplete}
          disabled={completing}
        >
          <CheckCircle2 size={16} />
          {completing ? "Completing..." : "Complete Conversation"}
        </button>
      )}
    </div>
  );
}

async function invalidateAll(
  queryClient: ReturnType<typeof useQueryClient>,
  patientId: string | number,
  visitId: string | number
) {
  await queryClient.invalidateQueries({
    queryKey: ["patients", patientId, "visits", visitId, "question-sessions"],
  });

  await queryClient.invalidateQueries({
    queryKey: ["patients", patientId, "visits", visitId],
  });

  await queryClient.invalidateQueries({
    queryKey: ["patients", patientId, "visits"],
  });

  await queryClient.invalidateQueries({
    queryKey: ["patients", patientId, "timeline"],
  });

  await queryClient.invalidateQueries({
    queryKey: ["activity-logs"],
  });
}
