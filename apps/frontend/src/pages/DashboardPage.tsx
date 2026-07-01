import { useQuery } from "@tanstack/react-query";
import {
  Users,
  CalendarDays,
  Clock,
  FileText,
  ClipboardList,
  Bell,
} from "lucide-react";
import { getDashboardOverview } from "../lib/api";
import { Link } from "react-router";

const summaryIcons = {
  total_patients: Users,
  today_visits: CalendarDays,
  pending_followups: Clock,
  prescriptions_saved: FileText,
  unread_notifications: Bell,
};

const summaryLabels = {
  total_patients: "Total Patients",
  today_visits: "Today’s Visits",
  pending_followups: "Pending Follow-ups",
  prescriptions_saved: "Prescriptions Saved",
  unread_notifications: "Unread Notifications",
};

export function DashboardPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ["dashboard", "overview"],
    queryFn: getDashboardOverview,
  });

  if (isLoading) {
    return <div className="panel">Loading dashboard...</div>;
  }

  if (isError || !data) {
    return <div className="panel error">Unable to load dashboard.</div>;
  }

  return (
    <div className="dashboard-page">
      <section className="hero-panel">
        <div>
          <p className="eyebrow">Welcome back</p>
          <h1>Dr. {data.doctor.name}</h1>
          <p>
            Run the full clinical workflow from patient record to timeline,
            repertorization, prescription, fee, and printable documents.
          </p>
        </div>

        <div className="hero-actions">
          <Link to="/patients/new" className="primary-link">
            New Patient
          </Link>
          <Link to="/patients" className="secondary-link">
            View Patients
          </Link>
        </div>
      </section>

      <section className="stats-grid">
        {Object.entries(data.summary).map(([key, value]) => {
          const Icon = summaryIcons[key as keyof typeof summaryIcons];
          const label = summaryLabels[key as keyof typeof summaryLabels];

          return (
            <article className="stat-card" key={key}>
              <div className="stat-icon">
                <Icon size={22} />
              </div>
              <div>
                <p>{label}</p>
                <h2>{value}</h2>
              </div>
            </article>
          );
        })}
      </section>

      <section className="two-column">
        <article className="panel">
          <div className="panel-heading">
            <ClipboardList size={20} />
            <h3>Clinical Workflow</h3>
          </div>

          <div className="workflow-list">
            {data.clinical_workflow.map((item, index) => (
              <div className="workflow-item" key={item.title}>
                <div className="step-number">{index + 1}</div>
                <div>
                  <h4>{item.title}</h4>
                  <p>{item.description}</p>
                </div>
                <span className={`status-pill ${item.status}`}>
                  {item.status.replace("_", " ")}
                </span>
              </div>
            ))}
          </div>
        </article>

        <article className="panel">
          <div className="panel-heading">
            <FileText size={20} />
            <h3>Demo Walkthrough</h3>
          </div>

          <div className="next-list">
            {[
              ["Open Demo Patient", "Review the constitutional case timeline."],
              ["Open Demo Visit", "Show rubrics, repertorization, prescription, and fee."],
              ["Print Documents", "Open case sheet and prescription print pages."],
            ].map(([title, description], index) => (
              <div className="next-item" key={title}>
                <div className="step-number">{index + 1}</div>
                <div>
                  <h4>{title}</h4>
                  <p>{description}</p>
                </div>
              </div>
            ))}
          </div>
        </article>
      </section>

      <section className="panel">
        <div className="panel-heading">
          <div>
            <h3>Recent Activity</h3>
            <p className="panel-subtitle">
              Latest visits, prescriptions, and fee records.
            </p>
          </div>
        </div>

        {data.recent_activity.length === 0 ? (
          <p className="empty-state">No recent activity yet.</p>
        ) : (
          <div className="activity-list">
            {data.recent_activity.map((activity, index) => (
              <div className="activity-item" key={`${activity.type}-${index}`}>
                <div>
                  <strong>{activity.title}</strong>
                  <p>{activity.description}</p>
                </div>

                <span>
                  {activity.created_at
                    ? new Date(activity.created_at).toLocaleDateString()
                    : ""}
                </span>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
