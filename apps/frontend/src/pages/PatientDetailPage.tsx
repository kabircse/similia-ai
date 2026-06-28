import { Link, useParams } from "react-router";
import { useQuery } from "@tanstack/react-query";
import { getPatient } from "../lib/api";

export function PatientDetailPage() {
  const { patientId } = useParams();

  const { data: patient, isLoading, isError } = useQuery({
    queryKey: ["patients", patientId],
    queryFn: () => getPatient(patientId as string),
    enabled: Boolean(patientId),
  });

  if (isLoading) {
    return <div className="panel">Loading patient...</div>;
  }

  if (isError || !patient) {
    return <div className="panel error">Unable to load patient.</div>;
  }

  return (
    <div className="page-stack">
      <section className="page-header">
        <div>
          <p className="eyebrow">Patient Profile</p>
          <h1>{patient.name}</h1>
          <p>
            Basic patient information. Visits and clinical timeline will be
            connected in upcoming issues.
          </p>
        </div>

        <div className="header-actions">
          <Link to="/patients" className="secondary-link">
            Back
          </Link>
          <Link to={`/patients/${patient.id}/edit`} className="primary-link">
            Edit Patient
          </Link>
        </div>
      </section>

      <section className="detail-grid">
        <article className="panel">
          <h3>Identity</h3>
          <dl className="detail-list">
            <div>
              <dt>Age</dt>
              <dd>{patient.age_years ?? "-"}</dd>
            </div>
            <div>
              <dt>Gender</dt>
              <dd>{patient.gender ?? "-"}</dd>
            </div>
            <div>
              <dt>Marital Status</dt>
              <dd>{patient.marital_status ?? "-"}</dd>
            </div>
            <div>
              <dt>Occupation</dt>
              <dd>{patient.occupation ?? "-"}</dd>
            </div>
          </dl>
        </article>

        <article className="panel">
          <h3>Contact</h3>
          <dl className="detail-list">
            <div>
              <dt>Phone</dt>
              <dd>{patient.phone ?? "-"}</dd>
            </div>
            <div>
              <dt>Emergency Contact</dt>
              <dd>{patient.emergency_contact ?? "-"}</dd>
            </div>
            <div>
              <dt>Address</dt>
              <dd>{patient.address ?? "-"}</dd>
            </div>
          </dl>
        </article>

        <article className="panel full-panel">
          <h3>Notes</h3>
          <p className="notes-text">{patient.notes || "No notes added."}</p>
        </article>

        <article className="panel full-panel">
          <h3>Clinical Timeline</h3>
          <p className="empty-state">
            Visits, case-taking, prescriptions, fee records, and follow-ups will
            appear here after upcoming issues.
          </p>
        </article>
      </section>
    </div>
  );
}