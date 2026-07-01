import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  AlertTriangle,
  Gauge,
  HelpCircle,
  Repeat2,
  ShieldCheck,
  TimerReset,
} from "lucide-react";
import {
  generatePotencyGuidance,
  getPotencyGuidanceRuns,
  type PotencyCasePhase,
  type PotencyGuidanceRun,
  type PotencyPathology,
  type PotencySensitivity,
  type PotencyVitality,
} from "../../lib/api";

type PotencyGuidancePanelProps = {
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

export function PotencyGuidancePanel({
  patientId,
  visitId,
}: PotencyGuidancePanelProps) {
  const queryClient = useQueryClient();
  const [casePhase, setCasePhase] = useState<PotencyCasePhase>("unclear");
  const [sensitivity, setSensitivity] =
    useState<PotencySensitivity>("unclear");
  const [vitality, setVitality] = useState<PotencyVitality>("unclear");
  const [pathology, setPathology] = useState<PotencyPathology>("unclear");

  const runsQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "potency-guidance"],
    queryFn: () => getPotencyGuidanceRuns(patientId, visitId),
  });

  const generateMutation = useMutation({
    mutationFn: () =>
      generatePotencyGuidance(patientId, visitId, {
        case_phase: casePhase,
        patient_sensitivity: sensitivity,
        vitality_level: vitality,
        pathology_depth: pathology,
        include_organon: true,
        include_philosophy: true,
        include_follow_up_context: true,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits", visitId, "potency-guidance"],
      });
      await queryClient.invalidateQueries({ queryKey: ["activity-logs"] });
    },
  });

  const latestRun = runsQuery.data?.data?.[0];

  return (
    <section className="panel potency-guidance-panel">
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>
            <Gauge size={20} /> Potency Guidance Assistant
          </h3>
          <p className="panel-subtitle">
            Potency, repetition, wait-and-watch, and aggravation considerations.
          </p>
        </div>
      </div>

      <div className="safety-note">
        AI gives potency considerations. Doctor decides final potency, repetition,
        wait-and-watch, or remedy change.
      </div>

      <div className="potency-input-grid">
        <label>
          Case Phase
          <select
            className="method-select"
            value={casePhase}
            onChange={(event) =>
              setCasePhase(event.target.value as PotencyCasePhase)
            }
          >
            <option value="unclear">Auto / Unclear</option>
            <option value="acute">Acute</option>
            <option value="chronic">Chronic</option>
            <option value="constitutional">Constitutional</option>
            <option value="follow_up">Follow-up</option>
          </select>
        </label>

        <label>
          Sensitivity
          <select
            className="method-select"
            value={sensitivity}
            onChange={(event) =>
              setSensitivity(event.target.value as PotencySensitivity)
            }
          >
            <option value="unclear">Auto / Unclear</option>
            <option value="low">Low</option>
            <option value="moderate">Moderate</option>
            <option value="high">High</option>
          </select>
        </label>

        <label>
          Vitality
          <select
            className="method-select"
            value={vitality}
            onChange={(event) =>
              setVitality(event.target.value as PotencyVitality)
            }
          >
            <option value="unclear">Auto / Unclear</option>
            <option value="low">Low</option>
            <option value="moderate">Moderate</option>
            <option value="high">High</option>
          </select>
        </label>

        <label>
          Pathology Depth
          <select
            className="method-select"
            value={pathology}
            onChange={(event) =>
              setPathology(event.target.value as PotencyPathology)
            }
          >
            <option value="unclear">Auto / Unclear</option>
            <option value="functional">Functional</option>
            <option value="structural">Structural</option>
            <option value="advanced_pathology">Advanced Pathology</option>
          </select>
        </label>
      </div>

      <div className="inline-actions">
        <button
          className="primary-button inline-button"
          onClick={() => generateMutation.mutate()}
          disabled={generateMutation.isPending}
        >
          <Gauge size={16} />
          {generateMutation.isPending ? "Generating..." : "Generate Guidance"}
        </button>
      </div>

      {generateMutation.isError && (
        <div className="form-error">
          {apiErrorMessage(
            generateMutation.error,
            "Unable to generate potency guidance. Make sure FastAPI is running and knowledge chunks are imported."
          )}
        </div>
      )}

      {runsQuery.isError && (
        <div className="form-error">
          {apiErrorMessage(runsQuery.error, "Unable to load potency guidance.")}
        </div>
      )}

      {runsQuery.isLoading && (
        <p className="empty-state">Loading potency guidance...</p>
      )}

      {latestRun ? (
        <PotencyGuidanceCard run={latestRun} />
      ) : (
        !runsQuery.isLoading && (
          <p className="empty-state">No potency guidance generated yet.</p>
        )
      )}
    </section>
  );
}

function PotencyGuidanceCard({ run }: { run: PotencyGuidanceRun }) {
  const options = run.options ?? [];
  const cautions = run.cautions ?? [];

  return (
    <article className="potency-card">
      <div className="potency-header">
        <div>
          <p className="eyebrow">Latest Potency Guidance</p>
          <h4>{run.remedy_name ?? "Selected Remedy"}</h4>
          <p>
            {formatValue(run.case_phase)} | vitality{" "}
            {formatValue(run.vitality_level)} | sensitivity{" "}
            {formatValue(run.sensitivity_level)} | pathology{" "}
            {formatValue(run.pathology_depth)}
          </p>
        </div>
      </div>

      {run.guidance_summary && (
        <p className="potency-summary">{run.guidance_summary}</p>
      )}

      <div className="potency-guidance-grid">
        <GuidanceBox
          icon="repeat"
          title="Repetition Guidance"
          text={run.repetition_guidance}
        />
        <GuidanceBox
          icon="wait"
          title="Wait and Watch"
          text={run.wait_and_watch_guidance}
        />
        <GuidanceBox
          icon="alert"
          title="Aggravation Review"
          text={run.aggravation_guidance}
        />
      </div>

      {options.length > 0 && (
        <div className="potency-options">
          <h5>Potency Consideration Options</h5>

          {options.map((option) => (
            <div
              className={`potency-option ${option.potency_range}`}
              key={option.id}
            >
              <div>
                <strong>{option.potency_label ?? option.potency_range}</strong>
                {option.rationale && <p>{option.rationale}</p>}
                {option.repetition_note && (
                  <small>Repetition: {option.repetition_note}</small>
                )}
                {option.caution && <small>Caution: {option.caution}</small>}
              </div>

              <span>{Number(option.suitability_score || 0).toFixed(0)}</span>

              {option.source_chunks.length > 0 && (
                <details className="source-details">
                  <summary>Sources</summary>

                  <div className="source-chunk-list">
                    {option.source_chunks.map((chunk, index) => (
                      <div className="source-chunk" key={index}>
                        <strong>
                          {textValue(chunk.source_title ?? chunk.title ?? "Source")}
                        </strong>
                        <p>{textValue(chunk.content)}</p>
                      </div>
                    ))}
                  </div>
                </details>
              )}
            </div>
          ))}
        </div>
      )}

      {cautions.length > 0 && (
        <div className="warning-panel">
          <strong>Cautions</strong>
          <ul>
            {cautions.map((item, index) => (
              <li key={`${item}-${index}`}>{item}</li>
            ))}
          </ul>
        </div>
      )}

      <div className="potency-guidance-grid">
        <ListBox
          title="Follow-up Questions"
          items={run.follow_up_questions ?? []}
        />
        <ListBox
          title="Doctor Review Points"
          items={run.doctor_review_points ?? []}
        />
      </div>

      {run.safety_note && <div className="safety-note">{run.safety_note}</div>}
    </article>
  );
}

function GuidanceBox({
  icon,
  title,
  text,
}: {
  icon: "repeat" | "wait" | "alert";
  title: string;
  text: string | null;
}) {
  if (!text) {
    return null;
  }

  const Icon =
    icon === "repeat" ? Repeat2 : icon === "wait" ? TimerReset : AlertTriangle;

  return (
    <div className="potency-guidance-box">
      <h5>
        <Icon size={16} />
        {title}
      </h5>
      <p>{text}</p>
    </div>
  );
}

function ListBox({ title, items }: { title: string; items: string[] }) {
  if (!items.length) {
    return null;
  }

  return (
    <div className="potency-guidance-box">
      <h5>
        <ShieldCheck size={16} />
        {title}
      </h5>

      <ul>
        {items.map((item, index) => (
          <li key={`${item}-${index}`}>
            <HelpCircle size={14} />
            <span>{item}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

function formatValue(value: string | null) {
  return (value ?? "unclear").replaceAll("_", " ");
}

function textValue(value: unknown) {
  if (typeof value === "string") {
    return value;
  }

  if (value === null || value === undefined) {
    return "";
  }

  return String(value);
}
