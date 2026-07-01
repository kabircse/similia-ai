import { useState, type ReactNode } from "react";
import { useQuery } from "@tanstack/react-query";
import {
  Activity,
  AlertTriangle,
  Banknote,
  CalendarClock,
  FileText,
  HeartPulse,
  Pill,
  Users,
} from "lucide-react";
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import {
  getClinicalDashboard,
  type ClinicalDashboard,
  type ClinicalDashboardPeriod,
  type DashboardFollowUpItem,
} from "../lib/api";

const chartColors = ["#145c43", "#2f6f97", "#9a5b22", "#7a4f9a", "#58636b", "#b43d3d"];

export function ClinicalDashboardPage() {
  const [period, setPeriod] = useState<ClinicalDashboardPeriod>("30d");

  const dashboardQuery = useQuery({
    queryKey: ["clinical-dashboard", period],
    queryFn: () => getClinicalDashboard({ period }),
  });

  const dashboard = dashboardQuery.data;

  return (
    <main className="page clinical-dashboard-page">
      <div className="page-header">
        <div>
          <p className="eyebrow">Clinical Analytics</p>
          <h1>Clinical Dashboard</h1>
          <p>
            Track activity, follow-up outcomes, safety review, revenue, and due
            follow-ups.
          </p>
        </div>

        <label className="dashboard-filter">
          Period
          <select
            className="method-select"
            value={period}
            onChange={(event) =>
              setPeriod(event.target.value as ClinicalDashboardPeriod)
            }
          >
            <option value="7d">Last 7 Days</option>
            <option value="30d">Last 30 Days</option>
            <option value="90d">Last 90 Days</option>
            <option value="this_month">This Month</option>
            <option value="last_month">Last Month</option>
            <option value="this_year">This Year</option>
          </select>
        </label>
      </div>

      <div className="safety-note">
        Analytics are for clinical audit and practice improvement. They are not
        guaranteed cure-rate claims or medical proof.
      </div>

      {dashboardQuery.isLoading && (
        <p className="empty-state">Loading clinical dashboard...</p>
      )}

      {dashboardQuery.isError && (
        <div className="form-error">Unable to load clinical dashboard.</div>
      )}

      {dashboard && <DashboardContent dashboard={dashboard} />}
    </main>
  );
}

function DashboardContent({ dashboard }: { dashboard: ClinicalDashboard }) {
  return (
    <div className="clinical-dashboard-grid">
      <section className="dashboard-kpis">
        <KpiCard
          icon={<Users size={20} />}
          label="New Patients"
          value={dashboard.kpis.new_patients}
        />

        <KpiCard
          icon={<Activity size={20} />}
          label="Visits"
          value={dashboard.kpis.visits}
        />

        <KpiCard
          icon={<CalendarClock size={20} />}
          label="Follow-ups"
          value={dashboard.kpis.follow_up_visits}
        />

        <KpiCard
          icon={<Pill size={20} />}
          label="Prescriptions"
          value={dashboard.kpis.prescriptions}
        />

        <KpiCard
          icon={<HeartPulse size={20} />}
          label="Avg Progress"
          value={dashboard.kpis.average_progress_score}
        />

        <KpiCard
          icon={<FileText size={20} />}
          label="Handouts"
          value={dashboard.kpis.patient_handouts}
        />

        <KpiCard
          icon={<Banknote size={20} />}
          label="Paid"
          value={dashboard.finance.paid_amount}
          prefix="৳"
        />

        <KpiCard
          icon={<AlertTriangle size={20} />}
          label="Red Flags"
          value={dashboard.safety.red_flag_count}
        />
      </section>

      <section className="dashboard-chart-card wide">
        <h3>Visits by Day</h3>
        <ChartBox empty={!dashboard.clinic_activity.visits_by_day.length}>
          <ResponsiveContainer width="100%" height={260}>
            <AreaChart data={dashboard.clinic_activity.visits_by_day}>
              <CartesianGrid strokeDasharray="3 3" stroke="#d7dfd8" />
              <XAxis dataKey="date" tick={{ fontSize: 12 }} />
              <YAxis allowDecimals={false} />
              <Tooltip />
              <Area
                type="monotone"
                dataKey="total"
                stroke="#145c43"
                fill="#cfe9d7"
              />
            </AreaChart>
          </ResponsiveContainer>
        </ChartBox>
      </section>

      <section className="dashboard-chart-card">
        <h3>New Patients by Day</h3>
        <ChartBox empty={!dashboard.clinic_activity.new_patients_by_day.length}>
          <ResponsiveContainer width="100%" height={260}>
            <AreaChart data={dashboard.clinic_activity.new_patients_by_day}>
              <CartesianGrid strokeDasharray="3 3" stroke="#d7dfd8" />
              <XAxis dataKey="date" tick={{ fontSize: 12 }} />
              <YAxis allowDecimals={false} />
              <Tooltip />
              <Area
                type="monotone"
                dataKey="total"
                stroke="#2f6f97"
                fill="#d7ecf8"
              />
            </AreaChart>
          </ResponsiveContainer>
        </ChartBox>
      </section>

      <section className="dashboard-chart-card">
        <h3>Visit Types</h3>
        <ChartBox empty={!dashboard.clinic_activity.visit_type_distribution.length}>
          <ResponsiveContainer width="100%" height={260}>
            <PieChart>
              <Pie
                data={dashboard.clinic_activity.visit_type_distribution}
                dataKey="total"
                nameKey="visit_type"
                outerRadius={90}
                label
              >
                {dashboard.clinic_activity.visit_type_distribution.map((item, index) => (
                  <Cell
                    key={item.visit_type}
                    fill={chartColors[index % chartColors.length]}
                  />
                ))}
              </Pie>
              <Tooltip />
            </PieChart>
          </ResponsiveContainer>
        </ChartBox>
      </section>

      <section className="dashboard-chart-card">
        <h3>Outcome Distribution</h3>
        <ChartBox empty={!dashboard.outcomes.response_level_distribution.length}>
          <ResponsiveContainer width="100%" height={260}>
            <PieChart>
              <Pie
                data={dashboard.outcomes.response_level_distribution}
                dataKey="total"
                nameKey="response_level"
                outerRadius={90}
                label
              >
                {dashboard.outcomes.response_level_distribution.map((item, index) => (
                  <Cell
                    key={item.response_level}
                    fill={chartColors[index % chartColors.length]}
                  />
                ))}
              </Pie>
              <Tooltip />
            </PieChart>
          </ResponsiveContainer>
        </ChartBox>
      </section>

      <section className="dashboard-chart-card">
        <h3>Progress Score Trend</h3>
        <ChartBox empty={!dashboard.outcomes.progress_score_trend.length}>
          <ResponsiveContainer width="100%" height={260}>
            <LineChart data={dashboard.outcomes.progress_score_trend}>
              <CartesianGrid strokeDasharray="3 3" stroke="#d7dfd8" />
              <XAxis dataKey="date" tick={{ fontSize: 12 }} />
              <YAxis />
              <Tooltip />
              <Line
                type="monotone"
                dataKey="average_score"
                stroke="#2f6f97"
                strokeWidth={2}
                dot={false}
              />
            </LineChart>
          </ResponsiveContainer>
        </ChartBox>
      </section>

      <section className="dashboard-chart-card">
        <h3>Top Remedies</h3>
        <ChartBox empty={!dashboard.remedies.top_prescribed_remedies.length}>
          <ResponsiveContainer width="100%" height={280}>
            <BarChart data={dashboard.remedies.top_prescribed_remedies}>
              <CartesianGrid strokeDasharray="3 3" stroke="#d7dfd8" />
              <XAxis dataKey="remedy" tick={{ fontSize: 12 }} />
              <YAxis allowDecimals={false} />
              <Tooltip />
              <Bar dataKey="total" fill="#9a5b22" />
            </BarChart>
          </ResponsiveContainer>
        </ChartBox>
      </section>

      <section className="dashboard-chart-card">
        <h3>Prescription Review Status</h3>
        <ChartBox empty={!dashboard.safety.prescription_review_status.length}>
          <ResponsiveContainer width="100%" height={280}>
            <BarChart data={dashboard.safety.prescription_review_status}>
              <CartesianGrid strokeDasharray="3 3" stroke="#d7dfd8" />
              <XAxis dataKey="review_status" tick={{ fontSize: 12 }} />
              <YAxis allowDecimals={false} />
              <Tooltip />
              <Bar dataKey="total" fill="#7a4f9a" />
            </BarChart>
          </ResponsiveContainer>
        </ChartBox>
      </section>

      <section className="dashboard-chart-card">
        <h3>Top Potencies</h3>
        <ChartBox empty={!dashboard.remedies.top_potencies.length}>
          <ResponsiveContainer width="100%" height={240}>
            <BarChart data={dashboard.remedies.top_potencies}>
              <CartesianGrid strokeDasharray="3 3" stroke="#d7dfd8" />
              <XAxis dataKey="potency" />
              <YAxis allowDecimals={false} />
              <Tooltip />
              <Bar dataKey="total" fill="#58636b" />
            </BarChart>
          </ResponsiveContainer>
        </ChartBox>
      </section>

      <section className="dashboard-list-card">
        <h3>Finance Overview</h3>
        <div className="finance-grid">
          <Metric label="Total" value={`৳ ${dashboard.finance.total_amount}`} />
          <Metric label="Paid" value={`৳ ${dashboard.finance.paid_amount}`} />
          <Metric label="Due" value={`৳ ${dashboard.finance.due_amount}`} />
          <Metric label="Unpaid" value={dashboard.finance.unpaid_count} />
          <Metric label="Partial" value={dashboard.finance.partial_count} />
          <Metric label="Paid Count" value={dashboard.finance.paid_count} />
        </div>
      </section>

      <section className="dashboard-list-card">
        <h3>Overdue Follow-ups</h3>
        <FollowUpList items={dashboard.follow_ups.overdue} />
      </section>

      <section className="dashboard-list-card">
        <h3>Due Next 7 Days</h3>
        <FollowUpList items={dashboard.follow_ups.due_next_7_days} />
      </section>

      <section className="dashboard-list-card">
        <h3>Recent Red Flags</h3>
        {dashboard.safety.recent_red_flags.length > 0 ? (
          <div className="dashboard-alert-list">
            {dashboard.safety.recent_red_flags.map((item) => (
              <div className="dashboard-alert critical" key={item.id}>
                <strong>{item.patient_name ?? "Unknown patient"}</strong>
                <p>{item.red_flags.join("; ")}</p>
                {item.summary && <small>{item.summary}</small>}
              </div>
            ))}
          </div>
        ) : (
          <p className="empty-state">No recent red flags.</p>
        )}
      </section>

      <section className="dashboard-list-card">
        <h3>Latest Outcome Cases</h3>
        {dashboard.outcomes.latest_outcome_cases.length > 0 ? (
          <div className="dashboard-alert-list">
            {dashboard.outcomes.latest_outcome_cases.map((item) => (
              <div className="dashboard-alert" key={item.id}>
                <strong>
                  {item.patient_name ?? "Unknown patient"} |{" "}
                  {item.response_level ?? "unclear"}
                </strong>
                {item.summary && <p>{item.summary}</p>}
                <small>Progress score: {item.progress_score}</small>
              </div>
            ))}
          </div>
        ) : (
          <p className="empty-state">No outcome analysis yet.</p>
        )}
      </section>

      <section className="dashboard-list-card">
        <h3>Recent Alerts</h3>
        {dashboard.recent_alerts.length > 0 ? (
          <div className="dashboard-alert-list">
            {dashboard.recent_alerts.map((item, index) => (
              <div
                className={`dashboard-alert ${item.severity}`}
                key={`${item.type}-${item.patient_id}-${index}`}
              >
                <strong>{item.title}</strong>
                {item.description && <p>{item.description}</p>}
                <small>{item.patient_name ?? "No patient linked"}</small>
              </div>
            ))}
          </div>
        ) : (
          <p className="empty-state">No recent alerts.</p>
        )}
      </section>
    </div>
  );
}

function KpiCard({
  icon,
  label,
  value,
  prefix,
}: {
  icon: ReactNode;
  label: string;
  value: number;
  prefix?: string;
}) {
  return (
    <article className="dashboard-kpi-card">
      <div>{icon}</div>
      <span>{label}</span>
      <strong>
        {prefix ? `${prefix} ` : ""}
        {value}
      </strong>
    </article>
  );
}

function Metric({ label, value }: { label: string; value: string | number }) {
  return (
    <div>
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

function ChartBox({
  children,
  empty,
}: {
  children: ReactNode;
  empty: boolean;
}) {
  if (empty) {
    return <p className="empty-state chart-empty">No chart data yet.</p>;
  }

  return <div className="chart-box">{children}</div>;
}

function FollowUpList({ items }: { items: DashboardFollowUpItem[] }) {
  if (!items.length) {
    return <p className="empty-state">No follow-ups found.</p>;
  }

  return (
    <div className="dashboard-alert-list">
      {items.map((item) => (
        <div className="dashboard-alert" key={item.prescription_id}>
          <strong>{item.patient_name ?? "Unknown patient"}</strong>
          <p>
            {item.follow_up_date ?? "No date"} | {item.remedy_name ?? "No remedy"}{" "}
            {item.potency ?? ""}
          </p>
          {item.patient_phone && <small>{item.patient_phone}</small>}
        </div>
      ))}
    </div>
  );
}
