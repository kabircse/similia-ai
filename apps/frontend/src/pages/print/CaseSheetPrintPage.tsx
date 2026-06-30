import type { ReactNode } from "react";
import { Link, useParams } from "react-router";
import { useQuery } from "@tanstack/react-query";
import { Printer } from "lucide-react";
import { getCaseSheetPrintData } from "../../lib/api";
import { formatCaseSectionValue } from "../../lib/caseSectionFormat";

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="print-section">
      <h2>{title}</h2>
      {children}
    </section>
  );
}

function EmptyText({ value }: { value?: string | number | null }) {
  const text = value === null || value === undefined ? "" : String(value);

  return <span>{text.trim() !== "" ? text : "-"}</span>;
}

export function CaseSheetPrintPage() {
  const { patientId, visitId } = useParams();

  const { data, isLoading, isError } = useQuery({
    queryKey: ["print", "case-sheet", patientId, visitId],
    queryFn: () => getCaseSheetPrintData(patientId as string, visitId as string),
    enabled: Boolean(patientId && visitId),
  });

  if (isLoading) {
    return <div className="print-loading">Loading case sheet...</div>;
  }

  if (isError || !data) {
    return <div className="print-loading">Unable to load case sheet.</div>;
  }

  const clinicContact = [
    data.clinic.phone,
    data.clinic.email,
    data.clinic.website,
  ]
    .filter(Boolean)
    .join(" / ");

  return (
    <main className="print-page">
      <div className="print-actions no-print">
        <Link to={`/patients/${patientId}/visits/${visitId}`} className="secondary-link">
          Back to Visit
        </Link>

        <button className="primary-button inline-button" onClick={() => window.print()}>
          <Printer size={16} />
          Print / Save PDF
        </button>
      </div>

      <article className="print-sheet">
        <header className="print-header">
          <div className="print-clinic-heading">
            {data.clinic.logo_url && (
              <img
                src={data.clinic.logo_url}
                alt=""
                className="print-clinic-logo"
              />
            )}
            <h1>{data.clinic.name}</h1>
            <p>{data.clinic.tagline}</p>
            {data.clinic.address && <p>{data.clinic.address}</p>}
            {clinicContact && <p>{clinicContact}</p>}
          </div>

          <div className="print-doc-title">
            <h2>Doctor Case Sheet</h2>
            <p>Generated: {new Date(data.generated_at).toLocaleString()}</p>
          </div>
        </header>

        <Section title="Patient Information">
          <div className="print-grid">
            <p>
              <strong>Name:</strong> {data.patient.name}
            </p>
            <p>
              <strong>Age:</strong> <EmptyText value={data.patient.age_years} />
            </p>
            <p>
              <strong>Gender:</strong> <EmptyText value={data.patient.gender} />
            </p>
            <p>
              <strong>Phone:</strong> <EmptyText value={data.patient.phone} />
            </p>
            <p>
              <strong>Occupation:</strong> <EmptyText value={data.patient.occupation} />
            </p>
            <p>
              <strong>Marital Status:</strong>{" "}
              <EmptyText value={data.patient.marital_status} />
            </p>
            <p className="print-full">
              <strong>Address:</strong> <EmptyText value={data.patient.address} />
            </p>
            <p className="print-full">
              <strong>Emergency Contact:</strong>{" "}
              <EmptyText value={data.patient.emergency_contact} />
            </p>
          </div>
        </Section>

        <Section title="Visit Summary">
          <div className="print-grid">
            <p>
              <strong>Visit Date:</strong> <EmptyText value={data.visit.visit_date} />
            </p>
            <p>
              <strong>Visit Type:</strong> <EmptyText value={data.visit.visit_type} />
            </p>
            <p>
              <strong>Status:</strong> <EmptyText value={data.visit.status} />
            </p>
            <p>
              <strong>Case Source:</strong> <EmptyText value={data.visit.case_source} />
            </p>
            <p>
              <strong>Next Follow-up:</strong>{" "}
              <EmptyText value={data.visit.next_follow_up_date} />
            </p>
            <p className="print-full">
              <strong>Chief Complaint:</strong>
              <br />
              <EmptyText value={data.visit.chief_complaint} />
            </p>
          </div>
        </Section>

        <Section title="Raw Case Notes">
          <p className="print-paragraph">
            <EmptyText value={data.visit.raw_case_text} />
          </p>
        </Section>

        <Section title="Classical Case-Taking Sections">
          {Object.keys(data.visit.case_sections ?? {}).length === 0 ? (
            <p>-</p>
          ) : (
            <div className="print-two-column-list">
              {Object.entries(data.visit.case_sections ?? {}).map(([key, value]) => (
                <div key={key} className="print-mini-box">
                  <strong>{key.replaceAll("_", " ")}</strong>
                  <p>{formatCaseSectionValue(value)}</p>
                </div>
              ))}
            </div>
          )}
        </Section>

        {data.visit.red_flags && data.visit.red_flags.length > 0 && (
          <Section title="Red Flags">
            <ul>
              {data.visit.red_flags.map((flag) => (
                <li key={flag}>{flag}</li>
              ))}
            </ul>
          </Section>
        )}

        {data.visit.missing_questions && data.visit.missing_questions.length > 0 && (
          <Section title="Missing Questions">
            <ul>
              {data.visit.missing_questions.map((question) => (
                <li key={question}>{question}</li>
              ))}
            </ul>
          </Section>
        )}

        <Section title="Selected Rubrics">
          {data.rubrics.length === 0 ? (
            <p>-</p>
          ) : (
            <table className="print-table">
              <thead>
                <tr>
                  <th>Rubric</th>
                  <th>Type</th>
                  <th>Importance</th>
                  <th>Weight</th>
                  <th>Essential</th>
                </tr>
              </thead>
              <tbody>
                {data.rubrics.map((rubric) => (
                  <tr key={rubric.id}>
                    <td>{rubric.rubric_path || "-"}</td>
                    <td>{rubric.symptom_type}</td>
                    <td>{rubric.importance}</td>
                    <td>{rubric.weight}</td>
                    <td>{rubric.is_essential ? "Yes" : "No"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Section>

        <Section title="Repertorization Results">
          {data.repertorization_runs.length === 0 ? (
            <p>-</p>
          ) : (
            data.repertorization_runs.map((run) => (
              <div key={run.id} className="print-run-block">
                <h3>{run.method.toUpperCase()}</h3>
                <table className="print-table">
                  <thead>
                    <tr>
                      <th>Rank</th>
                      <th>Remedy</th>
                      <th>Score</th>
                      <th>Coverage</th>
                      <th>Essential</th>
                    </tr>
                  </thead>
                  <tbody>
                    {run.results.map((result) => (
                      <tr key={`${run.id}-${result.rank}-${result.remedy_code}`}>
                        <td>{result.rank}</td>
                        <td>{result.remedy_name}</td>
                        <td>{result.total_score}</td>
                        <td>{result.rubric_coverage}</td>
                        <td>{result.essential_coverage}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ))
          )}
        </Section>

        <Section title="Final Prescription">
          {data.prescription ? (
            <div className="print-prescription-box">
              <p>
                <strong>Remedy:</strong> {data.prescription.remedy_name}
              </p>
              <p>
                <strong>Potency:</strong> {data.prescription.potency}
              </p>
              <p>
                <strong>Repetition:</strong>{" "}
                <EmptyText value={data.prescription.repetition} />
              </p>
              <p>
                <strong>Dose:</strong>{" "}
                <EmptyText value={data.prescription.dose_instruction} />
              </p>
              <p>
                <strong>Reason:</strong> <EmptyText value={data.prescription.reason} />
              </p>
              <p>
                <strong>Advice:</strong> <EmptyText value={data.prescription.advice} />
              </p>
              <p>
                <strong>Food / Lifestyle:</strong>{" "}
                <EmptyText value={data.prescription.food_lifestyle_note} />
              </p>
              <p>
                <strong>Follow-up:</strong>{" "}
                <EmptyText value={data.prescription.follow_up_date} />
              </p>
            </div>
          ) : (
            <p>-</p>
          )}
        </Section>

        <Section title="Fee Record">
          {data.fee ? (
            <div className="print-grid">
              <p>
                <strong>Consultation:</strong> {data.fee.currency}{" "}
                {data.fee.consultation_fee}
              </p>
              <p>
                <strong>Medicine:</strong> {data.fee.currency} {data.fee.medicine_fee}
              </p>
              <p>
                <strong>Discount:</strong> {data.fee.currency}{" "}
                {data.fee.discount_amount}
              </p>
              <p>
                <strong>Total:</strong> {data.fee.currency} {data.fee.total_amount}
              </p>
              <p>
                <strong>Paid:</strong> {data.fee.currency} {data.fee.paid_amount}
              </p>
              <p>
                <strong>Due:</strong> {data.fee.currency} {data.fee.due_amount}
              </p>
              <p>
                <strong>Status:</strong> {data.fee.payment_status}
              </p>
              <p>
                <strong>Payment Date:</strong>{" "}
                <EmptyText value={data.fee.payment_date} />
              </p>
              <p className="print-full">
                <strong>Note:</strong> <EmptyText value={data.fee.note} />
              </p>
            </div>
          ) : (
            <p>-</p>
          )}
        </Section>

        <Section title="Doctor Notes">
          <p className="print-paragraph">
            <EmptyText value={data.visit.doctor_notes} />
          </p>
        </Section>

        <footer className="print-footer">
          <div>
            <p>Doctor: {data.doctor.name}</p>
            {data.doctor.qualification && <p>{data.doctor.qualification}</p>}
          </div>
          <p>Signature: __________________________</p>
        </footer>

        {data.clinic.case_sheet_footer && (
          <footer className="print-footer-note">
            {data.clinic.case_sheet_footer}
          </footer>
        )}
      </article>
    </main>
  );
}
