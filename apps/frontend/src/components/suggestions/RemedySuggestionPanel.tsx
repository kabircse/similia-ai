import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  BrainCircuit,
  CheckCircle2,
  HelpCircle,
  ShieldAlert,
  XCircle,
} from "lucide-react";
import {
  generateRemedySuggestions,
  getRemedySuggestionRuns,
  type AiResponseLanguage,
  type RemedySuggestionItem,
  type RemedySuggestionMethod,
} from "../../lib/api";

type RemedySuggestionPanelProps = {
  patientId: string;
  visitId: string;
};

type Method = RemedySuggestionMethod | "";

function suggestionErrorMessage(error: unknown) {
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

  return "Unable to generate suggestion. Run repertorization first and make sure FastAPI is running.";
}

export function RemedySuggestionPanel({
  patientId,
  visitId,
}: RemedySuggestionPanelProps) {
  const queryClient = useQueryClient();
  const [method, setMethod] = useState<Method>("weighted");
  const [limit, setLimit] = useState(3);
  const [responseLanguage, setResponseLanguage] =
    useState<AiResponseLanguage>("auto");

  const runsQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "remedy-suggestions"],
    queryFn: () => getRemedySuggestionRuns(patientId, visitId),
  });

  const generateMutation = useMutation({
    mutationFn: () =>
      generateRemedySuggestions(patientId, visitId, {
        method,
        limit,
        include_potency: true,
        include_relationship: true,
        include_medical_safety: true,
        include_organon: true,
        response_language: responseLanguage,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: [
          "patients",
          patientId,
          "visits",
          visitId,
          "remedy-suggestions",
        ],
      });
      await queryClient.invalidateQueries({ queryKey: ["activity-logs"] });
    },
  });

  const latestRun = runsQuery.data?.data?.[0];

  return (
    <section className="panel remedy-suggestion-panel">
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>
            <BrainCircuit size={20} /> AI Remedy Suggestion
          </h3>
          <p className="panel-subtitle">Current visit evidence and ranked remedies.</p>
        </div>
      </div>

      <div className="safety-note">
        AI suggests. Repertory calculates. Knowledge supports. Doctor decides.
      </div>

      <div className="inline-actions">
        <label className="compact-control">
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

        <label className="compact-control">
          Method
          <select
            className="method-select"
            value={method}
            onChange={(event) => setMethod(event.target.value as Method)}
          >
            <option value="weighted">Weighted</option>
            <option value="cross">Cross</option>
            <option value="eliminative">Eliminative</option>
            <option value="">Latest</option>
          </select>
        </label>

        <label className="compact-control">
          Remedies
          <select
            className="method-select"
            value={limit}
            onChange={(event) => setLimit(Number(event.target.value))}
          >
            <option value={3}>Top 3</option>
            <option value={5}>Top 5</option>
          </select>
        </label>

        <button
          className="primary-button inline-button"
          onClick={() => generateMutation.mutate()}
          disabled={generateMutation.isPending}
        >
          <BrainCircuit size={16} />
          {generateMutation.isPending ? "Generating..." : "Generate"}
        </button>
      </div>

      {generateMutation.isError && (
        <div className="form-error">
          {suggestionErrorMessage(generateMutation.error)}
        </div>
      )}

      {runsQuery.isLoading && <p className="empty-state">Loading suggestions...</p>}

      {latestRun ? (
        <div className="suggestion-run">
          <div className="suggestion-run-header">
            <div>
              <strong>Latest suggestion</strong>
              <p>
                Method: {latestRun.method ?? "latest"} ·{" "}
                {latestRun.created_at
                  ? new Date(latestRun.created_at).toLocaleString()
                  : ""}
              </p>
            </div>
          </div>

          {latestRun.safety_note && (
            <div className="warning-panel">{latestRun.safety_note}</div>
          )}

          <div className="suggestion-list">
            {latestRun.items.map((item) => (
              <SuggestionItemCard item={item} key={item.id} />
            ))}
          </div>
        </div>
      ) : (
        <p className="empty-state">No AI remedy suggestion generated yet.</p>
      )}
    </section>
  );
}

function SuggestionItemCard({ item }: { item: RemedySuggestionItem }) {
  return (
    <article className="suggestion-card">
      <div className="suggestion-card-header">
        <div>
          <p className="eyebrow">Rank #{item.rank}</p>
          <h4>{item.remedy_name}</h4>
          <p>{item.remedy_code}</p>
        </div>

        <div className="confidence-pill">
          {safeNumber(item.confidence_score).toFixed(0)}%
        </div>
      </div>

      {item.summary && <p className="suggestion-summary">{item.summary}</p>}

      <div className="score-grid">
        <ScoreBox label="Repertory" value={item.repertory_score} />
        <ScoreBox label="Materia Medica" value={item.materia_medica_score} />
        <ScoreBox label="Knowledge" value={item.knowledge_score} />
      </div>

      <details open className="suggestion-details">
        <summary>Evidence Matrix</summary>
        <div className="evidence-matrix">
          {item.evidence_matrix.map((row, index) => (
            <div className="evidence-row" key={`${row.rubric_path}-${index}`}>
              {row.covered ? <CheckCircle2 size={16} /> : <XCircle size={16} />}
              <span>{row.rubric_path}</span>
              <small>
                {row.importance || "normal"} · weight {row.weight ?? "-"}
                {row.is_essential ? " · essential" : ""}
              </small>
            </div>
          ))}
        </div>
      </details>

      <SuggestionList title="Matching Points" items={item.matching_points} />
      <SuggestionList
        title="Differentiating Points"
        items={item.differentiating_points}
        icon="question"
      />
      <SuggestionList
        title="Missing Questions"
        items={item.missing_questions}
        icon="question"
      />

      {item.potency_considerations.length > 0 && (
        <SourceBlock
          title="Potency / Philosophy"
          items={item.potency_considerations}
        />
      )}

      {item.relationship_notes.length > 0 && (
        <SourceBlock title="Relationship Notes" items={item.relationship_notes} />
      )}

      {item.medical_safety_notes.length > 0 && (
        <SourceBlock
          title="Medical Safety Notes"
          items={item.medical_safety_notes}
        />
      )}

      {item.source_chunks.length > 0 && (
        <details className="source-details">
          <summary>Retrieved source chunks</summary>
          <div className="source-chunk-list">
            {item.source_chunks.map((chunk, index) => (
              <div className="source-chunk" key={index}>
                <strong>{textValue(chunk.source_title ?? chunk.type)}</strong>
                <p>{textValue(chunk.content)}</p>
              </div>
            ))}
          </div>
        </details>
      )}
    </article>
  );
}

function ScoreBox({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <span>{label}</span>
      <strong>{safeNumber(value).toFixed(0)}</strong>
    </div>
  );
}

function SuggestionList({
  title,
  items,
  icon = "check",
}: {
  title: string;
  items: string[];
  icon?: "check" | "question";
}) {
  if (!items.length) {
    return null;
  }

  return (
    <div className="suggestion-section">
      <h5>
        {icon === "check" ? <CheckCircle2 size={16} /> : <HelpCircle size={16} />}
        {title}
      </h5>
      <ul>
        {items.map((item, index) => (
          <li key={index}>{item}</li>
        ))}
      </ul>
    </div>
  );
}

function SourceBlock({
  title,
  items,
}: {
  title: string;
  items: Array<Record<string, unknown>>;
}) {
  return (
    <div className="suggestion-section">
      <h5>
        <ShieldAlert size={16} />
        {title}
      </h5>

      <div className="source-chunk-list">
        {items.map((item, index) => (
          <div className="source-chunk" key={index}>
            <strong>{textValue(item.source_title ?? item.title)}</strong>
            <p>{textValue(item.note ?? item.content)}</p>
          </div>
        ))}
      </div>
    </div>
  );
}

function safeNumber(value: string | number | null | undefined) {
  const number = Number(value);
  return Number.isFinite(number) ? number : 0;
}

function textValue(value: unknown) {
  if (value === null || value === undefined || value === "") {
    return "Source";
  }

  return String(value);
}
