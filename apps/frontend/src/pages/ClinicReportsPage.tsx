import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Download, FileText, Printer, Sparkles } from "lucide-react";
import {
  clinicReportCsvUrl,
  generateClinicReport,
  getClinicReports,
  type AiResponseLanguage,
  type ClinicReportRun,
} from "../lib/api";

type Period = "this_month" | "last_month" | "this_year" | "custom";

function metricLabel(key: string) {
  return key.replaceAll("_", " ");
}

export function ClinicReportsPage() {
  const queryClient = useQueryClient();

  const [period, setPeriod] = useState<Period>("last_month");
  const [responseLanguage, setResponseLanguage] =
    useState<AiResponseLanguage>("auto");

  const reportsQuery = useQuery({
    queryKey: ["clinic-reports"],
    queryFn: () => getClinicReports(),
  });

  const generateMutation = useMutation({
    mutationFn: () =>
      generateClinicReport({
        report_type: period === "this_year" ? "yearly" : "monthly",
        period,
        response_language: responseLanguage,
        include_finance: true,
        include_safety: true,
        include_follow_ups: true,
        include_recommendations: true,
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["clinic-reports"],
      });

      await queryClient.invalidateQueries({
        queryKey: ["activity-logs"],
      });
    },
  });

  const reports = reportsQuery.data?.data ?? [];
  const latestReport = reports[0];

  return (
    <main className="page clinic-reports-page">
      <div className="page-header">
        <div>
          <p className="eyebrow">Reports</p>
          <h1>Clinic Reports</h1>
          <p>
            Generate internal clinic reports for activity, outcomes, safety,
            finance, and follow-up audit.
          </p>
        </div>
      </div>

      <div className="safety-note">
        These reports are for internal audit and practice improvement only. Do
        not use them as public cure-rate or guaranteed outcome claims.
      </div>

      <section className="panel report-generator-panel">
        <div className="report-generator-row">
          <label>
            Period
            <select
              className="method-select"
              value={period}
              onChange={(event) => setPeriod(event.target.value as Period)}
            >
              <option value="last_month">Last Month</option>
              <option value="this_month">This Month</option>
              <option value="this_year">This Year</option>
            </select>
          </label>

          <label>
            Report Language
            <select
              className="method-select"
              value={responseLanguage}
              onChange={(event) =>
                setResponseLanguage(event.target.value as AiResponseLanguage)
              }
            >
              <option value="auto">Auto</option>
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
            <Sparkles size={16} />
            {generateMutation.isPending ? "Generating..." : "Generate Report"}
          </button>
        </div>

        {generateMutation.isError && (
          <div className="form-error">
            Unable to generate clinic report. Make sure backend and AI service
            are running.
          </div>
        )}
      </section>

      {reportsQuery.isLoading && <p className="empty-state">Loading reports...</p>}

      {reportsQuery.isError && (
        <div className="form-error">Unable to load clinic reports.</div>
      )}

      {latestReport && <ClinicReportCard report={latestReport} />}

      <section className="panel report-list-panel">
        <h3>Previous Reports</h3>

        <div className="report-list">
          {reports.map((report) => (
            <ClinicReportListItem report={report} key={report.id} />
          ))}

          {!reportsQuery.isLoading && reports.length === 0 && (
            <div className="report-empty-state">
              <FileText size={22} />
              <p>No reports generated yet.</p>
            </div>
          )}
        </div>
      </section>
    </main>
  );
}

function ClinicReportCard({ report }: { report: ClinicReportRun }) {
  return (
    <article className="clinic-report-card">
      <div className="report-card-header">
        <div>
          <p className="eyebrow">Latest Report</p>
          <h2>{report.title ?? "Clinic Report"}</h2>
          <p>
            {report.period_start} to {report.period_end} |{" "}
            {report.resolved_language ?? report.response_language}
          </p>
        </div>

        <ReportActions report={report} />
      </div>

      {report.executive_summary && (
        <p className="report-summary">{report.executive_summary}</p>
      )}

      <div className="report-metrics-grid">
        {Object.entries(report.key_metrics ?? {}).map(([key, value]) => (
          <div className="report-metric" key={key}>
            <span>{metricLabel(key)}</span>
            <strong>{String(value)}</strong>
          </div>
        ))}
      </div>

      <div className="report-section-grid">
        {report.sections.map((section) => (
          <section className={`report-section ${section.category}`} key={section.id}>
            <h3>{section.title}</h3>
            <p>{section.content}</p>
          </section>
        ))}
      </div>

      {report.safety_note && (
        <div className="safety-note">{report.safety_note}</div>
      )}
    </article>
  );
}

function ClinicReportListItem({ report }: { report: ClinicReportRun }) {
  return (
    <div className="report-list-item">
      <div>
        <strong>{report.title ?? "Clinic Report"}</strong>
        <p>
          {report.period_start} to {report.period_end} | {report.report_type}
        </p>
      </div>

      <ReportActions report={report} compact />
    </div>
  );
}

function ReportActions({
  report,
  compact = false,
}: {
  report: ClinicReportRun;
  compact?: boolean;
}) {
  function openPrint() {
    window.open(`/clinic-reports/${report.id}/print`, "_blank");
  }

  function downloadCsv() {
    window.open(clinicReportCsvUrl(report.id), "_blank");
  }

  return (
    <div className={compact ? "report-actions compact" : "report-actions"}>
      <button className="secondary-button inline-button" onClick={openPrint}>
        <Printer size={16} />
        Print
      </button>

      <button className="secondary-button inline-button" onClick={downloadCsv}>
        <Download size={16} />
        CSV
      </button>
    </div>
  );
}
