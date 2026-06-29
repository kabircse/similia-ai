import { Link } from "react-router";
import { useQuery } from "@tanstack/react-query";
import {
  CalendarDays,
  ClipboardList,
  CreditCard,
  FlaskConical,
  Pill,
} from "lucide-react";
import { getPatientTimeline } from "../../lib/api";
import type { PatientTimelineItem } from "../../lib/api";

type PatientTimelinePanelProps = {
  patientId: string;
};

function EmptyText({ value }: { value?: string | null }) {
  return <span>{value && value.trim() !== "" ? value : "-"}</span>;
}

function formatLabel(value: string | null | undefined) {
  if (!value) {
    return "-";
  }

  return value.replaceAll("_", " ");
}

function TimelineCard({
  patientId,
  item,
}: {
  patientId: string;
  item: PatientTimelineItem;
}) {
  return (
    <article className="timeline-card">
      <div className="timeline-marker">
        <CalendarDays size={18} />
      </div>

      <div className="timeline-content">
        <div className="timeline-card-header">
          <div>
            <p className="eyebrow">{formatLabel(item.visit.visit_type)}</p>
            <h4>{item.title}</h4>
            <p>
              Status: {formatLabel(item.visit.status)} | Source:{" "}
              {formatLabel(item.visit.case_source)}
            </p>
          </div>

          <Link
            to={`/patients/${patientId}/visits/${item.visit.id}`}
            className="primary-link"
          >
            Open Visit
          </Link>
        </div>

        <div className="timeline-section">
          <div className="timeline-section-title">
            <ClipboardList size={16} />
            <strong>Case</strong>
          </div>

          <p>
            <strong>Chief complaint:</strong>{" "}
            <EmptyText value={item.visit.chief_complaint} />
          </p>

          <p>
            <strong>Rubrics:</strong> {item.case_summary.rubrics_count} selected |{" "}
            {item.case_summary.essential_rubrics_count} essential
          </p>

          {item.case_summary.selected_rubrics.length > 0 && (
            <ul className="timeline-mini-list">
              {item.case_summary.selected_rubrics.map((rubric, index) => (
                <li key={`${rubric.rubric_path}-${index}`}>
                  {rubric.rubric_path || "Untitled rubric"}
                  {rubric.is_essential ? " | essential" : ""}
                </li>
              ))}
            </ul>
          )}
        </div>

        {item.repertorization.length > 0 && (
          <div className="timeline-section">
            <div className="timeline-section-title">
              <FlaskConical size={16} />
              <strong>Repertorization</strong>
            </div>

            <div className="timeline-run-list">
              {item.repertorization.map((run) => (
                <div className="timeline-run" key={run.id}>
                  <strong>{formatLabel(run.method)}</strong>

                  {run.top_results.length > 0 ? (
                    <p>
                      Top:{" "}
                      {run.top_results
                        .map(
                          (result) =>
                            `#${result.rank} ${result.remedy_name} (${result.total_score})`
                        )
                        .join(", ")}
                    </p>
                  ) : (
                    <p>No result stored.</p>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        <div className="timeline-split">
          <div className="timeline-section">
            <div className="timeline-section-title">
              <Pill size={16} />
              <strong>Prescription</strong>
            </div>

            {item.prescription ? (
              <>
                <p>
                  <strong>{item.prescription.remedy_name}</strong>{" "}
                  {item.prescription.potency}
                </p>
                <p>
                  Repetition: <EmptyText value={item.prescription.repetition} />
                </p>
                <p>
                  Follow-up:{" "}
                  <EmptyText value={item.prescription.follow_up_date} />
                </p>
                <p>Status: {formatLabel(item.prescription.status)}</p>
              </>
            ) : (
              <p>No prescription saved.</p>
            )}
          </div>

          <div className="timeline-section">
            <div className="timeline-section-title">
              <CreditCard size={16} />
              <strong>Fee</strong>
            </div>

            {item.fee ? (
              <>
                <p>
                  Total: {item.fee.currency} {item.fee.total_amount}
                </p>
                <p>
                  Paid: {item.fee.currency} {item.fee.paid_amount}
                </p>
                <p>
                  Due: {item.fee.currency} {item.fee.due_amount}
                </p>
                <p>Status: {formatLabel(item.fee.payment_status)}</p>
              </>
            ) : (
              <p>No fee record saved.</p>
            )}
          </div>
        </div>

        {item.visit.next_follow_up_date && (
          <div className="followup-strip">
            Next follow-up: {item.visit.next_follow_up_date}
          </div>
        )}
      </div>
    </article>
  );
}

export function PatientTimelinePanel({ patientId }: PatientTimelinePanelProps) {
  const timelineQuery = useQuery({
    queryKey: ["patients", patientId, "timeline"],
    queryFn: () => getPatientTimeline(patientId),
  });

  if (timelineQuery.isLoading) {
    return (
      <section className="panel full-panel">
        <h3>Patient Timeline</h3>
        <p className="empty-state">Loading timeline...</p>
      </section>
    );
  }

  if (timelineQuery.isError) {
    return (
      <section className="panel full-panel">
        <h3>Patient Timeline</h3>
        <div className="form-error">Unable to load patient timeline.</div>
      </section>
    );
  }

  const items = timelineQuery.data?.data ?? [];

  return (
    <section className="panel full-panel">
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>Patient Timeline</h3>
          <p className="panel-subtitle">
            {timelineQuery.data?.meta.total ?? 0} visit records, newest first.
          </p>
        </div>
      </div>

      {items.length === 0 ? (
        <p className="empty-state">
          No visit timeline yet. Create the first visit for this patient.
        </p>
      ) : (
        <div className="patient-timeline">
          {items.map((item) => (
            <TimelineCard key={item.id} patientId={patientId} item={item} />
          ))}
        </div>
      )}
    </section>
  );
}
