import { Link, useParams } from "react-router";
import { useQuery } from "@tanstack/react-query";
import { Printer } from "lucide-react";
import { getPrescriptionPrintData } from "../../lib/api";

function EmptyText({ value }: { value?: string | number | null }) {
  const text = value === null || value === undefined ? "" : String(value);

  return <span>{text.trim() !== "" ? text : "-"}</span>;
}

export function PrescriptionPrintPage() {
  const { patientId, visitId } = useParams();

  const { data, isLoading, isError } = useQuery({
    queryKey: ["print", "prescription", patientId, visitId],
    queryFn: () => getPrescriptionPrintData(patientId as string, visitId as string),
    enabled: Boolean(patientId && visitId),
  });

  if (isLoading) {
    return <div className="print-loading">Loading prescription...</div>;
  }

  if (isError || !data) {
    return <div className="print-loading">Unable to load prescription.</div>;
  }

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

      <article className="print-sheet prescription-sheet">
        <header className="print-header">
          <div>
            <h1>{data.clinic.name}</h1>
            <p>{data.clinic.tagline}</p>
            {data.clinic.address && <p>{data.clinic.address}</p>}
            {data.clinic.phone && <p>{data.clinic.phone}</p>}
          </div>

          <div className="print-doc-title">
            <h2>Prescription</h2>
            <p>Date: {data.visit.visit_date || "-"}</p>
          </div>
        </header>

        <section className="print-section">
          <h2>Patient</h2>
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
            <p className="print-full">
              <strong>Address:</strong> <EmptyText value={data.patient.address} />
            </p>
          </div>
        </section>

        <section className="print-section">
          <h2>Chief Complaint</h2>
          <p className="print-paragraph">
            <EmptyText value={data.visit.chief_complaint} />
          </p>
        </section>

        <section className="print-section prescription-main-box">
          <h2>Medicine</h2>

          {data.prescription ? (
            <>
              <div className="medicine-line">
                <strong>{data.prescription.remedy_name}</strong>
                <span>{data.prescription.potency}</span>
              </div>

              <div className="print-grid">
                <p>
                  <strong>Repetition:</strong>{" "}
                  <EmptyText value={data.prescription.repetition} />
                </p>
                <p>
                  <strong>Follow-up:</strong>{" "}
                  <EmptyText value={data.prescription.follow_up_date} />
                </p>
              </div>

              <p>
                <strong>Dose Instruction:</strong>
                <br />
                <EmptyText value={data.prescription.dose_instruction} />
              </p>

              <p>
                <strong>Advice:</strong>
                <br />
                <EmptyText value={data.prescription.advice} />
              </p>

              <p>
                <strong>Food / Lifestyle Note:</strong>
                <br />
                <EmptyText value={data.prescription.food_lifestyle_note} />
              </p>
            </>
          ) : (
            <p>No prescription saved for this visit.</p>
          )}
        </section>

        <footer className="print-footer prescription-footer">
          <div>
            <p>
              <strong>Doctor:</strong> {data.doctor.name}
            </p>
            <p>{data.doctor.email}</p>
          </div>

          <div>
            <p>Signature</p>
            <p>__________________________</p>
          </div>
        </footer>
      </article>
    </main>
  );
}
