import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Activity,
  AlertTriangle,
  ArrowDownCircle,
  ArrowUpCircle,
  HelpCircle,
  RotateCcw,
  ShieldAlert,
} from "lucide-react";
import {
  generateFollowUpAnalysis,
  getFollowUpAnalyses,
  type FollowUpAnalysisRun,
  type FollowUpProgressItem,
} from "../../lib/api";

type FollowUpAnalysisPanelProps = {
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

export function FollowUpAnalysisPanel({
  patientId,
  visitId,
}: FollowUpAnalysisPanelProps) {
  const queryClient = useQueryClient();

  const analysesQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "follow-up-analyses"],
    queryFn: () => getFollowUpAnalyses(patientId, visitId),
  });

  const generateMutation = useMutation({
    mutationFn: () =>
      generateFollowUpAnalysis(patientId, visitId, {
        include_timeline_context: true,
        limit_previous_visits: 3,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits", visitId, "follow-up-analyses"],
      });
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "timeline"],
      });
      await queryClient.invalidateQueries({ queryKey: ["activity-logs"] });
    },
  });

  const latestRun = analysesQuery.data?.data?.[0];

  return (
    <section className="panel followup-analysis-panel">
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>
            <Activity size={20} /> AI Follow-up Analysis
          </h3>
          <p className="panel-subtitle">Progress, safety, and next questions.</p>
        </div>
      </div>

      <div className="safety-note">
        AI analyzes progress. Doctor decides whether to wait, repeat, change potency,
        change remedy, or refer.
      </div>

      <div className="inline-actions">
        <button
          className="primary-button inline-button"
          onClick={() => generateMutation.mutate()}
          disabled={generateMutation.isPending}
        >
          <Activity size={16} />
          {generateMutation.isPending ? "Analyzing..." : "Generate Analysis"}
        </button>
      </div>

      {generateMutation.isError && (
        <div className="form-error">
          {apiErrorMessage(
            generateMutation.error,
            "Unable to generate follow-up analysis. Make sure this patient has a previous visit and FastAPI is running."
          )}
        </div>
      )}

      {analysesQuery.isError && (
        <div className="form-error">
          {apiErrorMessage(
            analysesQuery.error,
            "Unable to load follow-up analyses."
          )}
        </div>
      )}

      {analysesQuery.isLoading && (
        <p className="empty-state">Loading follow-up analyses...</p>
      )}

      {latestRun ? (
        <FollowUpAnalysisCard run={latestRun} />
      ) : (
        !analysesQuery.isLoading && (
          <p className="empty-state">
            No follow-up analysis yet. Add the current follow-up details first.
          </p>
        )
      )}
    </section>
  );
}

function FollowUpAnalysisCard({ run }: { run: FollowUpAnalysisRun }) {
  const score = Number(run.progress_score || 0);
  const redFlags = run.red_flags ?? [];
  const progressItems = run.progress_items ?? [];

  return (
    <article className={`followup-card ${run.response_level ?? "unclear"}`}>
      <div className="followup-header">
        <div>
          <p className="eyebrow">Latest Analysis</p>
          <h4>{formatResponseLevel(run.response_level)}</h4>
          <p>
            {run.created_at ? new Date(run.created_at).toLocaleString() : ""}
          </p>
        </div>

        <div className="progress-score" title="Progress score">
          {score.toFixed(0)}
        </div>
      </div>

      {run.analysis_summary && (
        <p className="followup-summary">{run.analysis_summary}</p>
      )}

      {run.remedy_response_assessment && (
        <div className="followup-remedy-response">
          <strong>Remedy Response</strong>
          <p>{run.remedy_response_assessment}</p>
        </div>
      )}

      <div className="followup-grid">
        <FollowUpList
          title="Improvement"
          icon="up"
          items={run.improvement_points ?? []}
        />
        <FollowUpList
          title="Worsening"
          icon="down"
          items={run.worsening_points ?? []}
        />
        <FollowUpList
          title="New Symptoms"
          icon="alert"
          items={run.new_symptoms ?? []}
        />
        <FollowUpList
          title="Old Symptoms Returned"
          icon="return"
          items={run.old_symptoms_returned ?? []}
        />
        <FollowUpList
          title="Possible Aggravation"
          icon="alert"
          items={run.possible_aggravation_signs ?? []}
        />
        <FollowUpList
          title="Unchanged"
          icon="question"
          items={run.unchanged_points ?? []}
        />
      </div>

      {redFlags.length > 0 && (
        <div className="warning-panel followup-warning">
          <strong>
            <ShieldAlert size={16} /> Red Flags
          </strong>
          <ul>
            {redFlags.map((item, index) => (
              <li key={`${item}-${index}`}>{item}</li>
            ))}
          </ul>
        </div>
      )}

      {progressItems.length > 0 && (
        <details className="source-details" open>
          <summary>Symptom Change Matrix</summary>
          <div className="progress-matrix">
            {progressItems.map((item) => (
              <ProgressRow item={item} key={item.id} />
            ))}
          </div>
        </details>
      )}

      <div className="followup-grid">
        <FollowUpList
          title="Suggested Questions"
          icon="question"
          items={run.suggested_follow_up_questions ?? []}
        />
        <FollowUpList
          title="Doctor Review Points"
          icon="question"
          items={run.doctor_review_points ?? []}
        />
        <FollowUpList
          title="Recommended Next Steps"
          icon="return"
          items={run.recommended_next_steps ?? []}
        />
      </div>

      {run.safety_note && <div className="safety-note">{run.safety_note}</div>}
    </article>
  );
}

function FollowUpList({
  title,
  items,
  icon,
}: {
  title: string;
  items: string[];
  icon: "up" | "down" | "alert" | "return" | "question";
}) {
  if (!items.length) {
    return null;
  }

  const Icon =
    icon === "up"
      ? ArrowUpCircle
      : icon === "down"
        ? ArrowDownCircle
        : icon === "alert"
          ? AlertTriangle
          : icon === "return"
            ? RotateCcw
            : HelpCircle;

  return (
    <div className="followup-list">
      <h5>
        <Icon size={16} />
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

function ProgressRow({ item }: { item: FollowUpProgressItem }) {
  return (
    <div className={`progress-row ${item.change_status}`}>
      <span>{statusIcon(item.change_status)}</span>
      <div>
        <strong>{item.symptom}</strong>
        {item.evidence && <p>{item.evidence}</p>}
      </div>
      <small>{formatStatus(item.change_status)}</small>
    </div>
  );
}

function formatResponseLevel(value: FollowUpAnalysisRun["response_level"]) {
  if (!value) {
    return "Unclear Response";
  }

  return value
    .split("_")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

function formatStatus(value: string) {
  return value.replaceAll("_", " ");
}

function statusIcon(status: string) {
  if (status === "improved" || status === "resolved") {
    return "+";
  }

  if (status === "worse") {
    return "-";
  }

  if (status === "new") {
    return "N";
  }

  if (status === "returned_old_symptom") {
    return "R";
  }

  return "~";
}
