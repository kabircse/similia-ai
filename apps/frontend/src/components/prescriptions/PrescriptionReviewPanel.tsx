import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  AlertTriangle,
  CheckCircle2,
  ClipboardCheck,
  ShieldCheck,
  XCircle,
} from "lucide-react";
import {
  generatePrescriptionReview,
  getPrescriptionReviewRuns,
  updatePrescriptionReviewCheck,
  type AiResponseLanguage,
  type PrescriptionReviewCheck,
  type PrescriptionReviewRun,
} from "../../lib/api";

type PrescriptionReviewPanelProps = {
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

export function PrescriptionReviewPanel({
  patientId,
  visitId,
}: PrescriptionReviewPanelProps) {
  const queryClient = useQueryClient();
  const [responseLanguage, setResponseLanguage] =
    useState<AiResponseLanguage>("auto");

  const runsQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "prescription-reviews"],
    queryFn: () => getPrescriptionReviewRuns(patientId, visitId),
  });

  const generateMutation = useMutation({
    mutationFn: () =>
      generatePrescriptionReview(patientId, visitId, {
        include_remedy_suggestion: true,
        include_potency_guidance: true,
        include_relationship_guidance: true,
        include_follow_up_analysis: true,
        response_language: responseLanguage,
      }),
    onSuccess: async () => {
      await invalidateReviewQueries(queryClient, patientId, visitId);
    },
  });

  const latestRun = runsQuery.data?.data?.[0];

  return (
    <section className="panel prescription-review-panel">
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>
            <ClipboardCheck size={20} /> Prescription Decision Review
          </h3>
          <p className="panel-subtitle">
            Final safety and completeness checkpoint before the doctor finalizes.
          </p>
        </div>
      </div>

      <div className="safety-note">
        AI reviews safety and completeness. Doctor confirms the checklist and
        makes the final prescription decision.
      </div>

      <div className="review-action-row">
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

        <button
          className="primary-button inline-button"
          onClick={() => generateMutation.mutate()}
          disabled={generateMutation.isPending}
        >
          <ShieldCheck size={16} />
          {generateMutation.isPending ? "Reviewing..." : "Generate Review"}
        </button>
      </div>

      {generateMutation.isError && (
        <div className="form-error">
          {apiErrorMessage(
            generateMutation.error,
            "Unable to generate prescription review. Save a draft prescription and make sure FastAPI is running."
          )}
        </div>
      )}

      {runsQuery.isError && (
        <div className="form-error">
          {apiErrorMessage(runsQuery.error, "Unable to load prescription reviews.")}
        </div>
      )}

      {runsQuery.isLoading && (
        <p className="empty-state">Loading prescription reviews...</p>
      )}

      {latestRun ? (
        <PrescriptionReviewCard
          run={latestRun}
          patientId={patientId}
          visitId={visitId}
        />
      ) : (
        !runsQuery.isLoading && (
          <p className="empty-state">
            No prescription review generated yet. Save a prescription draft, then
            generate the safety checklist.
          </p>
        )
      )}
    </section>
  );
}

function PrescriptionReviewCard({
  run,
  patientId,
  visitId,
}: {
  run: PrescriptionReviewRun;
  patientId: string | number;
  visitId: string | number;
}) {
  return (
    <article className={`prescription-review-card ${run.review_status}`}>
      <div className="review-header">
        <div>
          <p className="eyebrow">Latest Prescription Review</p>
          <h4>{formatReviewStatus(run.review_status)}</h4>
          <p>
            {run.remedy_name ?? "No remedy"} | {run.potency ?? "No potency"} |{" "}
            {run.repetition ?? "No repetition"}
          </p>
        </div>

        <div className="review-score">
          {Number(run.safety_score || 0).toFixed(0)}
        </div>
      </div>

      {run.review_summary && <p className="review-summary">{run.review_summary}</p>}

      <div className="review-guidance-grid">
        <GuidanceBox title="Decision Guidance" text={run.decision_guidance} />
        <GuidanceBox title="Risk Summary" text={run.risk_summary} />
      </div>

      {run.red_flags.length > 0 && (
        <div className="warning-panel">
          <strong>Red Flags</strong>
          <ul>
            {run.red_flags.map((item, index) => (
              <li key={`${item}-${index}`}>{item}</li>
            ))}
          </ul>
        </div>
      )}

      {run.missing_information.length > 0 && (
        <div className="warning-panel">
          <strong>Missing Information</strong>
          <ul>
            {run.missing_information.map((item, index) => (
              <li key={`${item}-${index}`}>{item}</li>
            ))}
          </ul>
        </div>
      )}

      <div className="checklist">
        <h5>Doctor Safety Checklist</h5>

        {run.checks.map((check) => (
          <ChecklistItem
            key={check.id}
            check={check}
            run={run}
            patientId={patientId}
            visitId={visitId}
          />
        ))}
      </div>

      <div className="review-guidance-grid">
        <ListBox title="Doctor Review Points" items={run.doctor_review_points} />
        <ListBox title="Recommended Actions" items={run.recommended_actions} />
      </div>

      {run.safety_note && <div className="safety-note">{run.safety_note}</div>}
    </article>
  );
}

function ChecklistItem({
  check,
  run,
  patientId,
  visitId,
}: {
  check: PrescriptionReviewCheck;
  run: PrescriptionReviewRun;
  patientId: string | number;
  visitId: string | number;
}) {
  const queryClient = useQueryClient();
  const [doctorNote, setDoctorNote] = useState(check.doctor_note ?? "");

  const updateMutation = useMutation({
    mutationFn: (status: "doctor_confirmed" | "doctor_overridden" | "pending") =>
      updatePrescriptionReviewCheck(patientId, visitId, run.id, check.id, {
        status,
        doctor_note: doctorNote || null,
      }),
    onSuccess: async () => {
      await invalidateReviewQueries(queryClient, patientId, visitId);
    },
  });

  const Icon =
    check.status === "passed" || check.status === "doctor_confirmed"
      ? CheckCircle2
      : check.status === "warning" || check.status === "failed"
        ? AlertTriangle
        : XCircle;

  return (
    <div className={`checklist-item ${check.severity} ${check.status}`}>
      <div className="checklist-main">
        <Icon size={18} />

        <div>
          <strong>{check.title}</strong>
          {check.description && <p>{check.description}</p>}
          {check.ai_assessment && <small>{check.ai_assessment}</small>}

          {check.evidence.length > 0 && (
            <ul className="check-evidence">
              {check.evidence.map((item, index) => (
                <li key={`${item}-${index}`}>{item}</li>
              ))}
            </ul>
          )}

          <textarea
            rows={2}
            value={doctorNote}
            onChange={(event) => setDoctorNote(event.target.value)}
            placeholder="Doctor note..."
          />

          <div className="inline-actions">
            <button
              className="secondary-button inline-button"
              onClick={() => updateMutation.mutate("doctor_confirmed")}
              disabled={updateMutation.isPending}
            >
              <ShieldCheck size={15} />
              Confirm
            </button>

            <button
              className="secondary-button inline-button"
              onClick={() => updateMutation.mutate("doctor_overridden")}
              disabled={updateMutation.isPending}
            >
              Override with Note
            </button>

            <button
              className="secondary-button inline-button"
              onClick={() => updateMutation.mutate("pending")}
              disabled={updateMutation.isPending}
            >
              Reset
            </button>
          </div>
        </div>
      </div>

      <span className="check-status">{check.status.replaceAll("_", " ")}</span>
    </div>
  );
}

function GuidanceBox({ title, text }: { title: string; text: string | null }) {
  if (!text) {
    return null;
  }

  return (
    <div className="review-guidance-box">
      <h5>{title}</h5>
      <p>{text}</p>
    </div>
  );
}

function ListBox({ title, items }: { title: string; items: string[] }) {
  if (!items.length) {
    return null;
  }

  return (
    <div className="review-guidance-box">
      <h5>{title}</h5>
      <ul>
        {items.map((item, index) => (
          <li key={`${item}-${index}`}>{item}</li>
        ))}
      </ul>
    </div>
  );
}

function formatReviewStatus(status: string) {
  return status
    .split("_")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

async function invalidateReviewQueries(
  queryClient: ReturnType<typeof useQueryClient>,
  patientId: string | number,
  visitId: string | number
) {
  await queryClient.invalidateQueries({
    queryKey: ["patients", patientId, "visits", visitId, "prescription-reviews"],
  });

  await queryClient.invalidateQueries({ queryKey: ["activity-logs"] });
}
