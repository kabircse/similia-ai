import { Link } from "react-router";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Activity, Filter } from "lucide-react";
import { getActivityLogs } from "../lib/api";

const categories = [
  "",
  "patient",
  "visit",
  "ai",
  "rubric",
  "repertorization",
  "materia_medica",
  "prescription",
  "fee",
  "print",
  "demo",
];

export function ActivityLogPage() {
  const [category, setCategory] = useState("");

  const logsQuery = useQuery({
    queryKey: ["activity-logs", category],
    queryFn: () =>
      getActivityLogs({
        category: category || undefined,
      }),
  });

  const logs = logsQuery.data?.data ?? [];

  return (
    <div className="page-stack">
      <section className="page-header">
        <div>
          <p className="eyebrow">Audit Trail</p>
          <h1>Activity Logs</h1>
          <p>
            Review important clinical and administrative actions performed in
            Similia AI.
          </p>
        </div>
      </section>

      <section className="panel">
        <div className="activity-filter">
          <div>
            <Filter size={16} />
            <strong>Filter by category</strong>
          </div>

          <select
            value={category}
            onChange={(event) => setCategory(event.target.value)}
          >
            {categories.map((item) => (
              <option key={item || "all"} value={item}>
                {item ? item.replaceAll("_", " ") : "All categories"}
              </option>
            ))}
          </select>
        </div>

        {logsQuery.isLoading && (
          <p className="empty-state">Loading activity logs...</p>
        )}

        {logsQuery.isError && (
          <div className="form-error">Unable to load activity logs.</div>
        )}

        {!logsQuery.isLoading && logs.length === 0 && (
          <p className="empty-state">No activity logs found.</p>
        )}

        {logs.length > 0 && (
          <div className="audit-log-list">
            {logs.map((log) => (
              <article className="audit-log-card" key={log.id}>
                <div className="audit-icon">
                  <Activity size={18} />
                </div>

                <div className="audit-content">
                  <div className="audit-header">
                    <div>
                      <strong>{log.title}</strong>
                      <p>
                        {log.category} / {log.action}
                      </p>
                    </div>

                    <span>
                      {log.created_at
                        ? new Date(log.created_at).toLocaleString()
                        : ""}
                    </span>
                  </div>

                  {log.description && <p>{log.description}</p>}

                  <div className="audit-meta">
                    {log.patient && (
                      <Link to={`/patients/${log.patient.id}`}>
                        Patient: {log.patient.name}
                      </Link>
                    )}

                    {log.patient_id && log.patient_visit_id && (
                      <Link
                        to={`/patients/${log.patient_id}/visits/${log.patient_visit_id}`}
                      >
                        Open Visit
                      </Link>
                    )}

                    {log.ip_address && <span>IP: {log.ip_address}</span>}
                  </div>

                  {Object.keys(log.metadata ?? {}).length > 0 && (
                    <details className="audit-details">
                      <summary>Metadata</summary>
                      <pre>{JSON.stringify(log.metadata, null, 2)}</pre>
                    </details>
                  )}
                </div>
              </article>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
