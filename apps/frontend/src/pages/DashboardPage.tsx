import { useQuery } from "@tanstack/react-query";
import {
  Users,
  CalendarDays,
  Clock,
  FileText,
  ClipboardList,
  Search,
  BookOpen,
  CheckCircle2,
} from "lucide-react";
import { getDashboardOverview } from "../lib/api";

const summaryIcons = {
  total_patients: Users,
  today_visits: CalendarDays,
  pending_followups: Clock,
  prescriptions_saved: FileText,
};

const summaryLabels = {
  total_patients: "Total Patients",
  today_visits: "Today’s Visits",
  pending_followups: "Pending Follow-ups",
  prescriptions_saved: "Prescriptions Saved",
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
            Start with patient registration, structured case-taking, rubrics,
            repertorization, materia medica comparison, and doctor-approved
            prescription.
          </p>
        </div>

        <div className="hero-actions">
          <button className="primary-button" disabled>
            New Patient
          </button>
          <button className="secondary-button" disabled>
            New Case
          </button>
          <span className="hint">Enabled in Issue #5 and #6</span>
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
            <Search size={20} />
            <h3>Next Build Focus</h3>
          </div>

          <div className="next-list">
            
          </div>
        </article>
      </section>

      <section className="panel">
        <div className="panel-heading">
          <Clock size={20} />
          <h3>Recent Activity</h3>
        </div>

        {data.recent_activity.length === 0 ? (
          <p className="empty-state">
            No clinical activity yet. 
          </p>
        ) : (
          <div>
            {data.recent_activity.map((activity) => (
              <div key={activity.title} className="activity-item">
                <h4>{activity.title}</h4>
                <p>{activity.description}</p>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}