import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Link } from "react-router";
import {
  AlertTriangle,
  CheckCircle2,
  ExternalLink,
  Inbox,
  RefreshCw,
  RotateCcw,
  XCircle,
} from "lucide-react";
import {
  getDoctorReviewQueue,
  updateDoctorReviewQueueItem,
} from "../lib/api";
import type { DoctorReviewQueueItem } from "../lib/api";

type QueueStatus = DoctorReviewQueueItem["status"] | "";
type QueuePriority = DoctorReviewQueueItem["priority"] | "";

export function DoctorReviewQueuePage() {
  const [status, setStatus] = useState<QueueStatus>("open");
  const [priority, setPriority] = useState<QueuePriority>("");

  const queueQuery = useQuery({
    queryKey: ["doctor-review-queue", status, priority],
    queryFn: () =>
      getDoctorReviewQueue({
        status,
        priority,
        category: "portal_submission",
      }),
  });

  const items = queueQuery.data?.data ?? [];

  return (
    <main className="page doctor-review-queue-page">
      <div className="page-header">
        <div>
          <p className="eyebrow">Doctor Workflow</p>
          <h1>Review Queue</h1>
          <p>Review patient portal submissions and warning-sign follow-ups.</p>
        </div>
      </div>

      <section className="panel review-queue-filter-panel">
        <label>
          Status
          <select
            className="method-select"
            value={status}
            onChange={(event) => setStatus(event.target.value as QueueStatus)}
          >
            <option value="">All</option>
            <option value="open">Open</option>
            <option value="in_review">In Review</option>
            <option value="completed">Completed</option>
            <option value="dismissed">Dismissed</option>
          </select>
        </label>

        <label>
          Priority
          <select
            className="method-select"
            value={priority}
            onChange={(event) =>
              setPriority(event.target.value as QueuePriority)
            }
          >
            <option value="">All</option>
            <option value="urgent">Urgent</option>
            <option value="high">High</option>
            <option value="normal">Normal</option>
            <option value="low">Low</option>
          </select>
        </label>
      </section>

      {queueQuery.isLoading && <p className="empty-state">Loading queue...</p>}

      {queueQuery.isError && (
        <div className="form-error">Unable to load review queue.</div>
      )}

      <section className="review-queue-list">
        {items.map((item) => (
          <ReviewQueueCard item={item} key={item.id} />
        ))}

        {!queueQuery.isLoading && items.length === 0 && (
          <div className="empty-state review-queue-empty">
            <Inbox size={28} />
            <span>No review queue items found.</span>
          </div>
        )}
      </section>
    </main>
  );
}

function ReviewQueueCard({ item }: { item: DoctorReviewQueueItem }) {
  const queryClient = useQueryClient();
  const [doctorNote, setDoctorNote] = useState(item.doctor_note ?? "");

  const updateMutation = useMutation({
    mutationFn: (nextStatus: DoctorReviewQueueItem["status"]) =>
      updateDoctorReviewQueueItem(item.id, {
        status: nextStatus,
        doctor_note: doctorNote || null,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["doctor-review-queue"] });
      await queryClient.invalidateQueries({
        queryKey: ["doctor-review-queue-summary"],
      });
    },
  });

  const isUrgent = item.priority === "urgent";
  const submission = item.follow_up_submission;

  return (
    <article className={`review-queue-card ${item.priority} ${item.status}`}>
      <div className="review-queue-card-header">
        <div>
          <p className="eyebrow">{labelize(item.category)}</p>
          <h3>
            {isUrgent && <AlertTriangle size={18} />}
            {item.title}
          </h3>
          <p>
            {item.patient?.name ?? "Unknown patient"} · {labelize(item.priority)} ·{" "}
            {labelize(item.status)}
          </p>
        </div>

        {item.action_url && (
          <Link className="secondary-button inline-button" to={item.action_url}>
            <ExternalLink size={16} />
            Open Case
          </Link>
        )}
      </div>

      {item.summary && <p className="queue-summary">{item.summary}</p>}

      {item.red_flags.length > 0 && (
        <div className="warning-panel review-queue-warning">
          <strong>Warning signs</strong>
          <ul>
            {item.red_flags.map((flag) => (
              <li key={flag}>{flag}</li>
            ))}
          </ul>
        </div>
      )}

      {submission && (
        <div className="queue-submission-box">
          <p>
            <strong>Overall:</strong> {labelize(submission.overall_change)}
          </p>
          <p>
            <strong>Main changes:</strong> {submission.main_changes || "N/A"}
          </p>
          <p>
            <strong>New symptoms:</strong> {submission.new_symptoms || "N/A"}
          </p>
          <p>
            <strong>Submitted:</strong> {formatDateTime(item.submitted_at)}
          </p>
        </div>
      )}

      <label className="review-queue-note-field">
        Doctor note
        <textarea
          rows={2}
          value={doctorNote}
          onChange={(event) => setDoctorNote(event.target.value)}
          placeholder="Doctor note..."
        />
      </label>

      <div className="inline-actions">
        {item.status !== "open" && (
          <button
            className="secondary-button inline-button"
            onClick={() => updateMutation.mutate("open")}
            disabled={updateMutation.isPending}
            type="button"
          >
            <RotateCcw size={16} />
            Reopen
          </button>
        )}

        <button
          className="secondary-button inline-button"
          onClick={() => updateMutation.mutate("in_review")}
          disabled={updateMutation.isPending}
          type="button"
        >
          <RefreshCw size={16} />
          In Review
        </button>

        <button
          className="primary-button inline-button"
          onClick={() => updateMutation.mutate("completed")}
          disabled={updateMutation.isPending}
          type="button"
        >
          <CheckCircle2 size={16} />
          Complete
        </button>

        <button
          className="secondary-button inline-button"
          onClick={() => updateMutation.mutate("dismissed")}
          disabled={updateMutation.isPending}
          type="button"
        >
          <XCircle size={16} />
          Dismiss
        </button>
      </div>

      {updateMutation.isError && (
        <div className="form-error">Unable to update queue item.</div>
      )}
    </article>
  );
}

function labelize(value: string) {
  return value.replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatDateTime(value: string | null) {
  return value ? new Date(value).toLocaleString() : "N/A";
}
