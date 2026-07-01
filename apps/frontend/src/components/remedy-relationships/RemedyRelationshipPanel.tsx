import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  AlertTriangle,
  Link2,
  Repeat2,
  ShieldAlert,
  Sparkles,
} from "lucide-react";
import {
  generateRemedyRelationship,
  getRemedyRelationshipRuns,
  type AiResponseLanguage,
  type RemedyRelationshipPurpose,
  type RemedyRelationshipRun,
} from "../../lib/api";

type RemedyRelationshipPanelProps = {
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

export function RemedyRelationshipPanel({
  patientId,
  visitId,
}: RemedyRelationshipPanelProps) {
  const queryClient = useQueryClient();
  const [primaryRemedyName, setPrimaryRemedyName] = useState("");
  const [comparisonRemedyName, setComparisonRemedyName] = useState("");
  const [purpose, setPurpose] =
    useState<RemedyRelationshipPurpose>("general");
  const [responseLanguage, setResponseLanguage] =
    useState<AiResponseLanguage>("auto");

  const runsQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "remedy-relationships"],
    queryFn: () => getRemedyRelationshipRuns(patientId, visitId),
  });

  const generateMutation = useMutation({
    mutationFn: () =>
      generateRemedyRelationship(patientId, visitId, {
        primary_remedy_name: primaryRemedyName.trim() || null,
        comparison_remedy_name: comparisonRemedyName.trim() || null,
        purpose,
        include_visit_context: true,
        include_follow_up_context: true,
        response_language: responseLanguage,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits", visitId, "remedy-relationships"],
      });
      await queryClient.invalidateQueries({ queryKey: ["activity-logs"] });
    },
  });

  const latestRun = runsQuery.data?.data?.[0];

  return (
    <section className="panel remedy-relationship-panel">
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>
            <Link2 size={20} /> Remedy Relationship Assistant
          </h3>
          <p className="panel-subtitle">
            Relationship, sequence, antidote, and inimical checks.
          </p>
        </div>
      </div>

      <div className="safety-note">
        AI explains remedy relationships. Doctor decides repeat, wait, antidote,
        remedy change, potency, or referral.
      </div>

      <div className="relationship-input-grid">
        <label>
          Primary Remedy
          <input
            value={primaryRemedyName}
            onChange={(event) => setPrimaryRemedyName(event.target.value)}
            placeholder="Example: Calcarea carbonica"
          />
        </label>

        <label>
          Comparison Remedy
          <input
            value={comparisonRemedyName}
            onChange={(event) => setComparisonRemedyName(event.target.value)}
            placeholder="Example: Sulphur"
          />
        </label>

        <label>
          Purpose
          <select
            className="method-select"
            value={purpose}
            onChange={(event) =>
              setPurpose(event.target.value as RemedyRelationshipPurpose)
            }
          >
            <option value="general">General</option>
            <option value="before_prescription">Before Prescription</option>
            <option value="follow_up">Follow-up</option>
            <option value="change_remedy">Change Remedy</option>
            <option value="antidote_check">Antidote Check</option>
            <option value="compare">Compare</option>
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
      </div>

      <div className="inline-actions">
        <button
          className="primary-button inline-button"
          onClick={() => generateMutation.mutate()}
          disabled={generateMutation.isPending || !primaryRemedyName.trim()}
        >
          <Sparkles size={16} />
          {generateMutation.isPending ? "Checking..." : "Check Relationship"}
        </button>
      </div>

      {generateMutation.isError && (
        <div className="form-error">
          {apiErrorMessage(
            generateMutation.error,
            "Unable to generate relationship guidance. Make sure relationship knowledge chunks are imported and FastAPI is running."
          )}
        </div>
      )}

      {runsQuery.isError && (
        <div className="form-error">
          {apiErrorMessage(
            runsQuery.error,
            "Unable to load remedy relationship guidance."
          )}
        </div>
      )}

      {runsQuery.isLoading && (
        <p className="empty-state">Loading relationship guidance...</p>
      )}

      {latestRun ? (
        <RemedyRelationshipCard run={latestRun} />
      ) : (
        !runsQuery.isLoading && (
          <p className="empty-state">No relationship guidance generated yet.</p>
        )
      )}
    </section>
  );
}

function RemedyRelationshipCard({ run }: { run: RemedyRelationshipRun }) {
  const findings = run.findings ?? [];
  const cautions = run.cautions ?? [];

  return (
    <article className="relationship-card">
      <div className="relationship-header">
        <div>
          <p className="eyebrow">Latest Relationship Review</p>
          <h4>
            {run.primary_remedy_name}
            {run.comparison_remedy_name
              ? ` -> ${run.comparison_remedy_name}`
              : ""}
          </h4>
          <p>
            Purpose: {formatValue(run.purpose)} | Language:{" "}
            {run.response_language}
          </p>
        </div>
      </div>

      {run.relationship_summary && (
        <p className="relationship-summary">{run.relationship_summary}</p>
      )}

      <div className="relationship-guidance-grid">
        <GuidanceBox
          icon="sequence"
          title="Sequence Guidance"
          text={run.sequence_guidance}
        />
        <GuidanceBox
          icon="antidote"
          title="Antidote Guidance"
          text={run.antidote_guidance}
        />
        <GuidanceBox
          icon="warning"
          title="Inimical Warning"
          text={run.inimical_warning}
        />
        <GuidanceBox
          icon="complementary"
          title="Complementary Note"
          text={run.complementary_note}
        />
      </div>

      {findings.length > 0 && (
        <div className="relationship-findings">
          <h5>Relationship Findings</h5>

          {findings.map((finding) => (
            <div
              className={`relationship-finding ${finding.relationship_type}`}
              key={finding.id}
            >
              <div>
                <strong>
                  {formatValue(finding.relationship_type)}
                  {finding.related_remedy_name
                    ? ` | ${finding.related_remedy_name}`
                    : ""}
                </strong>

                {finding.summary && <p>{finding.summary}</p>}
                {finding.clinical_note && <small>{finding.clinical_note}</small>}
                {finding.caution && <small>Caution: {finding.caution}</small>}
              </div>

              <span>{Number(finding.confidence_score || 0).toFixed(0)}</span>

              {finding.evidence.length > 0 && (
                <details className="source-details">
                  <summary>Evidence</summary>
                  <ul>
                    {finding.evidence.map((item, index) => (
                      <li key={`${item}-${index}`}>{item}</li>
                    ))}
                  </ul>
                </details>
              )}

              {finding.source_chunks.length > 0 && (
                <details className="source-details">
                  <summary>Source Chunks</summary>

                  <div className="source-chunk-list">
                    {finding.source_chunks.map((chunk, index) => (
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

      <div className="relationship-guidance-grid">
        <ListBox
          title="Doctor Review Points"
          items={run.doctor_review_points ?? []}
        />
        <ListBox
          title="Suggested Questions"
          items={run.suggested_questions ?? []}
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
  icon: "sequence" | "antidote" | "warning" | "complementary";
  title: string;
  text: string | null;
}) {
  if (!text) {
    return null;
  }

  const Icon =
    icon === "sequence"
      ? Repeat2
      : icon === "warning"
        ? AlertTriangle
        : icon === "antidote"
          ? ShieldAlert
          : Link2;

  return (
    <div className="relationship-guidance-box">
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
    <div className="relationship-guidance-box">
      <h5>
        <Link2 size={16} />
        {title}
      </h5>

      <ul>
        {items.map((item, index) => (
          <li key={`${item}-${index}`}>{item}</li>
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
