import { useEffect, useRef } from "react";
import { Link, useParams } from "react-router";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Printer } from "lucide-react";
import { getClinicReport, markClinicReportPrinted } from "../lib/api";

function metricLabel(key: string) {
  return key.replaceAll("_", " ");
}

export function ClinicReportPrintPage() {
  const { reportId } = useParams();
  const markedPrinted = useRef(false);

  const reportQuery = useQuery({
    queryKey: ["clinic-report-print", reportId],
    queryFn: () => getClinicReport(reportId as string),
    enabled: Boolean(reportId),
  });

  const { mutate: markPrinted } = useMutation({
    mutationFn: () => markClinicReportPrinted(reportId as string),
  });

  useEffect(() => {
    if (!reportQuery.data || markedPrinted.current) {
      return;
    }

    markedPrinted.current = true;
    markPrinted();

    const timer = window.setTimeout(() => window.print(), 500);

    return () => window.clearTimeout(timer);
  }, [markPrinted, reportQuery.data]);

  if (reportQuery.isLoading) {
    return <div className="print-loading">Loading report...</div>;
  }

  if (reportQuery.isError || !reportQuery.data) {
    return <div className="print-loading">Unable to load report.</div>;
  }

  const report = reportQuery.data;

  return (
    <main className="print-page clinic-report-print-page">
      <div className="print-actions no-print">
        <Link to="/clinic-reports" className="secondary-link">
          Back to Reports
        </Link>

        <button className="primary-button inline-button" onClick={() => window.print()}>
          <Printer size={16} />
          Print / Save PDF
        </button>
      </div>

      <article className="print-sheet clinic-report-print-sheet">
        <header className="clinic-report-print-header">
          <p>Internal Clinic Audit Report</p>
          <h1>{report.title ?? "Clinic Report"}</h1>
          <p>
            {report.period_start} to {report.period_end}
          </p>
        </header>

        <section className="clinic-report-print-summary">
          <h2>Executive Summary</h2>
          <p>{report.executive_summary ?? "-"}</p>
        </section>

        <section className="clinic-report-print-metrics">
          <h2>Key Metrics</h2>

          <table>
            <tbody>
              {Object.entries(report.key_metrics ?? {}).map(([key, value]) => (
                <tr key={key}>
                  <th>{metricLabel(key)}</th>
                  <td>{String(value)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>

        {report.sections.map((section) => (
          <section className="clinic-report-print-section" key={section.id}>
            <h2>{section.title}</h2>
            <p>{section.content}</p>
          </section>
        ))}

        {report.recommendations.length > 0 && (
          <section className="clinic-report-print-section">
            <h2>Recommendations</h2>
            <ul>
              {report.recommendations.map((item, index) => (
                <li key={`${item}-${index}`}>{item}</li>
              ))}
            </ul>
          </section>
        )}

        {report.limitations.length > 0 && (
          <section className="clinic-report-print-section">
            <h2>Limitations</h2>
            <ul>
              {report.limitations.map((item, index) => (
                <li key={`${item}-${index}`}>{item}</li>
              ))}
            </ul>
          </section>
        )}

        {report.safety_note && (
          <footer className="clinic-report-print-footer">
            <p>{report.safety_note}</p>
          </footer>
        )}
      </article>
    </main>
  );
}
