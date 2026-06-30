import { useEffect, useMemo, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Mic, MicOff, RotateCcw, Save } from "lucide-react";
import {
  getVoiceTranscripts,
  saveVoiceTranscript,
} from "../../lib/api";
import { AiTaskStatus } from "../ai/AiTaskStatus";

type VoiceCaseTakingPanelProps = {
  patientId: string | number;
  visitId: string | number;
};

type MergeMode = "append" | "prepend" | "replace";

type TranscriptSegment = {
  text: string;
  is_final: boolean;
  confidence: number | null;
  captured_at: string;
};

const languageOptions = [
  { label: "Bangla - Bangladesh", value: "bn-BD" },
  { label: "English - United States", value: "en-US" },
  { label: "English - India", value: "en-IN" },
  { label: "Hindi - India", value: "hi-IN" },
];

function errorMessage(error: unknown) {
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

  return "Unable to save voice transcript.";
}

export function VoiceCaseTakingPanel({
  patientId,
  visitId,
}: VoiceCaseTakingPanelProps) {
  const queryClient = useQueryClient();

  const recognitionRef = useRef<SpeechRecognition | null>(null);
  const startedAtRef = useRef<string | null>(null);
  const interimTextRef = useRef("");

  const [language, setLanguage] = useState("bn-BD");
  const [mergeMode, setMergeMode] = useState<MergeMode>("append");
  const [isListening, setIsListening] = useState(false);
  const [transcriptDraft, setTranscriptDraft] = useState("");
  const [interimText, setInterimText] = useState("");
  const [segments, setSegments] = useState<TranscriptSegment[]>([]);
  const [errorText, setErrorText] = useState<string | null>(null);
  const [voiceTaskId, setVoiceTaskId] = useState<number | null>(null);
  const isSupported = Boolean(
    window.SpeechRecognition ?? window.webkitSpeechRecognition
  );

  const transcriptText = useMemo(() => {
    return [transcriptDraft, interimText].filter(Boolean).join(" ").trim();
  }, [interimText, transcriptDraft]);

  const transcriptsQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "voice-transcripts"],
    queryFn: () => getVoiceTranscripts(patientId, visitId),
  });

  const refreshVisitData = async () => {
    await queryClient.invalidateQueries({
      queryKey: ["patients", patientId, "visits", visitId],
    });
    await queryClient.invalidateQueries({
      queryKey: ["patients", patientId, "visits"],
    });
    await queryClient.invalidateQueries({
      queryKey: ["patients", patientId, "timeline"],
    });
    await queryClient.invalidateQueries({ queryKey: ["notifications"] });
    await queryClient.invalidateQueries({ queryKey: ["dashboard", "overview"] });
  };

  const saveMutation = useMutation({
    mutationFn: () =>
      saveVoiceTranscript(patientId, visitId, {
        language,
        transcript_text: transcriptText,
        segments: segments as Array<Record<string, unknown>>,
        merge_to_case_text: true,
        merge_mode: mergeMode,
        started_at: startedAtRef.current,
        completed_at: new Date().toISOString(),
      }),
    onSuccess: async (result) => {
      setTranscriptDraft("");
      setInterimText("");
      interimTextRef.current = "";
      setSegments([]);
      setVoiceTaskId(result.queued_ai_task_id);
      startedAtRef.current = null;

      await refreshVisitData();
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits", visitId, "voice-transcripts"],
      });
      await queryClient.invalidateQueries({ queryKey: ["activity-logs"] });
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
      setIsListening(true);
      setErrorText(null);

      if (!startedAtRef.current) {
        startedAtRef.current = new Date().toISOString();
      }
    };

    recognition.onend = () => {
      setIsListening(false);

      if (interimTextRef.current) {
        setTranscriptDraft((current) =>
          `${current} ${interimTextRef.current}`.trim()
        );
        interimTextRef.current = "";
        setInterimText("");
      }
    };

    recognition.onerror = (event) => {
      setErrorText(event.message || event.error || "Voice recognition failed.");
      setIsListening(false);
    };

    recognition.onresult = (event) => {
      let interim = "";
      let final = "";

      const newSegments: TranscriptSegment[] = [];

      for (let index = event.resultIndex; index < event.results.length; index += 1) {
        const result = event.results[index];
        const alternative = result[0];
        const text = alternative.transcript.trim();

        if (!text) {
          continue;
        }

        if (result.isFinal) {
          final += `${text} `;
          newSegments.push({
            text,
            is_final: true,
            confidence:
              typeof alternative.confidence === "number"
                ? alternative.confidence
                : null,
            captured_at: new Date().toISOString(),
          });
        } else {
          interim += `${text} `;
        }
      }

      if (final) {
        setTranscriptDraft((current) => `${current} ${final}`.trim());
      }

      const nextInterim = interim.trim();
      interimTextRef.current = nextInterim;
      setInterimText(nextInterim);

      if (newSegments.length > 0) {
        setSegments((current) => [...current, ...newSegments]);
      }
    };

    recognitionRef.current = recognition;

    return () => {
      recognition.abort();
      recognitionRef.current = null;
    };
  }, [language]);

  function startListening() {
    if (!recognitionRef.current) {
      return;
    }

    setErrorText(null);
    saveMutation.reset();

    try {
      recognitionRef.current.lang = language;
      recognitionRef.current.start();
    } catch {
      setErrorText("Voice recognition is already running or unavailable.");
    }
  }

  function stopListening() {
    recognitionRef.current?.stop();
  }

  function resetTranscript() {
    stopListening();
    setTranscriptDraft("");
    setInterimText("");
    interimTextRef.current = "";
    setSegments([]);
    setErrorText(null);
    startedAtRef.current = null;
    saveMutation.reset();
  }

  if (!isSupported) {
    return (
      <section className="panel voice-panel">
        <div className="panel-heading">
          <div>
            <h3>
              <Mic size={20} /> Voice Case-Taking
            </h3>
            <p className="panel-subtitle">
              Browser speech recognition is unavailable in this browser.
            </p>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section className="panel voice-panel">
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>
            <Mic size={20} /> Voice Case-Taking
          </h3>
          <p className="panel-subtitle">
            Doctor-reviewed dictation for this visit.
          </p>
        </div>
      </div>

      <div className="voice-controls">
        <label>
          Language
          <select
            className="method-select"
            value={language}
            disabled={isListening}
            onChange={(event) => setLanguage(event.target.value)}
          >
            {languageOptions.map((item) => (
              <option value={item.value} key={item.value}>
                {item.label}
              </option>
            ))}
          </select>
        </label>

        <label>
          Save Mode
          <select
            className="method-select"
            value={mergeMode}
            onChange={(event) => setMergeMode(event.target.value as MergeMode)}
          >
            <option value="append">Append to Case Text</option>
            <option value="prepend">Prepend to Case Text</option>
            <option value="replace">Replace Case Text</option>
          </select>
        </label>

        <div className="voice-button-row">
          {!isListening ? (
            <button className="primary-button inline-button" onClick={startListening}>
              <Mic size={16} />
              Start Voice
            </button>
          ) : (
            <button className="danger-button inline-button" onClick={stopListening}>
              <MicOff size={16} />
              Stop
            </button>
          )}

          <button className="secondary-button inline-button" onClick={resetTranscript}>
            <RotateCcw size={16} />
            Reset
          </button>

          <button
            className="primary-button inline-button"
            onClick={() => saveMutation.mutate()}
            disabled={!transcriptText || saveMutation.isPending || isListening}
          >
            <Save size={16} />
            {saveMutation.isPending ? "Saving..." : "Save to Case Text"}
          </button>
        </div>
      </div>

      {errorText && <div className="form-error">{errorText}</div>}

      {saveMutation.isError && (
        <div className="form-error">{errorMessage(saveMutation.error)}</div>
      )}

      {isListening && (
        <div className="listening-indicator">
          <span />
          Listening
        </div>
      )}

      <label className="voice-transcript-editor">
        Transcript Preview
        <textarea
          value={transcriptDraft}
          onChange={(event) => setTranscriptDraft(event.target.value)}
          placeholder="Transcript preview"
          rows={7}
        />
      </label>

      {interimText && (
        <div className="voice-interim-text">
          <strong>Interim</strong>
          <p>{interimText}</p>
        </div>
      )}

      {saveMutation.isSuccess && (
        <div className="success-panel">
          Voice transcript saved, merged, and queued for AI structuring.
        </div>
      )}

      <AiTaskStatus taskId={voiceTaskId} onCompleted={refreshVisitData} />

      <details className="source-details">
        <summary>Previous Voice Transcripts</summary>

        <div className="source-chunk-list">
          {transcriptsQuery.isLoading && (
            <p className="empty-state">Loading voice transcripts...</p>
          )}

          {(transcriptsQuery.data?.data ?? []).map((transcript) => (
            <div className="source-chunk" key={transcript.id}>
              <strong>
                {transcript.language} ·{" "}
                {transcript.created_at
                  ? new Date(transcript.created_at).toLocaleString()
                  : ""}
              </strong>
              <p>{transcript.transcript_text}</p>
            </div>
          ))}

          {!transcriptsQuery.isLoading &&
            (transcriptsQuery.data?.data ?? []).length === 0 && (
              <p className="empty-state">No saved voice transcripts yet.</p>
            )}
        </div>
      </details>
    </section>
  );
}
