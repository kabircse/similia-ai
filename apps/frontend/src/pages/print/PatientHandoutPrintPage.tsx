import { useEffect } from "react";
import { Link, useParams } from "react-router";
import { useQuery } from "@tanstack/react-query";
import { Printer } from "lucide-react";
import { getPatientHandout } from "../../lib/api";

function valueText(value: unknown, fallback = "-") {
  if (value === null || value === undefined) {
    return fallback;
  }

  const text = String(value).trim();

  return text === "" ? fallback : text;
}

export function PatientHandoutPrintPage() {
  const { patientId, visitId, handoutId } = useParams();

  const handoutQuery = useQuery({
    queryKey: ["patient-handout-print", patientId, visitId, handoutId],
    queryFn: () =>
      getPatientHandout(patientId as string, visitId as string, handoutId as string),
    enabled: Boolean(patientId && visitId && handoutId),
  });

  useEffect(() => {
    if (handoutQuery.data) {
      const timer = window.setTimeout(() => window.print(), 400);

      return () => window.clearTimeout(timer);
    }
  }, [handoutQuery.data]);

  if (handoutQuery.isLoading) {
    return <div className="print-loading">Loading handout...</div>;
  }

  if (handoutQuery.isError || !handoutQuery.data) {
    return <div className="print-loading">Unable to load handout.</div>;
  }

  const handout = handoutQuery.data;
  const clinic = handout.clinic_snapshot;
  const caseSnapshot = handout.case_snapshot;
  const clinicName = valueText(
    clinic.clinic_name ?? clinic.name,
    "Clinic"
  );
  const doctorName = valueText(clinic.doctor_name, "");
  const clinicContact = [
    valueText(clinic.phone, ""),
    valueText(clinic.email, ""),
    valueText(clinic.website, ""),
  ]
    .filter(Boolean)
    .join(" / ");

  return (
    <main className="print-page handout-print-page">
      <div className="print-actions no-print">
        <Link to={`/patients/${patientId}/visits/${visitId}`} className="secondary-link">
          Back to Visit
        </Link>

        <button className="primary-button inline-button" onClick={() => window.print()}>
          <Printer size={16} />
          Print / Save PDF
        </button>
      </div>

      <article className="print-sheet handout-print-sheet">
        <header className="print-header handout-print-header">
          <div className="print-clinic-heading">
            {typeof clinic.logo_url === "string" && clinic.logo_url && (
              <img src={clinic.logo_url} alt="" className="print-clinic-logo" />
            )}
            <h1>{clinicName}</h1>
            {valueText(clinic.tagline, "") && <p>{valueText(clinic.tagline, "")}</p>}
            {doctorName && <p>{doctorName}</p>}
            {valueText(clinic.address, "") && <p>{valueText(clinic.address, "")}</p>}
            {clinicContact && <p>{clinicContact}</p>}
          </div>

          <div className="print-doc-title">
            <h2>{handout.title ?? "Patient Treatment Instructions"}</h2>
            <p>
              Date:{" "}
              {handout.created_at
                ? new Date(handout.created_at).toLocaleDateString()
                : "-"}
            </p>
          </div>
        </header>

        <section className="print-section">
          <h2>Patient</h2>
          <div className="print-grid">
            <p>
              <strong>Name:</strong> {valueText(caseSnapshot.patient_name)}
            </p>
            <p>
              <strong>Age:</strong> {valueText(caseSnapshot.age_years)}
            </p>
            <p>
              <strong>Gender:</strong> {valueText(caseSnapshot.gender)}
            </p>
            <p>
              <strong>Visit:</strong> {valueText(caseSnapshot.visit_date)}
            </p>
          </div>
        </section>

        {handout.sections.map((section) => (
          <section
            className={`print-section handout-print-section ${section.category}`}
            key={section.id}
          >
            <h2>{section.title}</h2>
            <p className="print-paragraph">{section.content}</p>
          </section>
        ))}

        {handout.warning_signs.length > 0 && (
          <section className="print-section handout-print-section warning">
            <h2>Warning Signs</h2>
            <ul>
              {handout.warning_signs.map((item, index) => (
                <li key={`${item}-${index}`}>{item}</li>
              ))}
            </ul>
          </section>
        )}

        {handout.do_and_dont.length > 0 && (
          <section className="print-section handout-print-section">
            <h2>Do and Don&apos;t</h2>
            <ul>
              {handout.do_and_dont.map((item, index) => (
                <li key={`${item}-${index}`}>{item}</li>
              ))}
            </ul>
          </section>
        )}

        {handout.safety_note && (
          <section className="print-section handout-print-section">
            <p className="print-paragraph">{handout.safety_note}</p>
          </section>
        )}

        {handout.footer_note && (
          <footer className="print-footer-note handout-print-footer">
            <p>{handout.footer_note}</p>
          </footer>
        )}
      </article>
    </main>
  );
}
