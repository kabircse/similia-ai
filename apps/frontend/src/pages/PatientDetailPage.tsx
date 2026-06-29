import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Link, useParams } from "react-router";
import { deletePatientVisit, getPatient, getPatientVisits } from "../lib/api";

export function PatientDetailPage() {
  const { patientId } = useParams();
  const queryClient = useQueryClient();

  const {
    data: patient,
    isLoading,
    isError,
  } = useQuery({
    queryKey: ["patients", patientId],
    queryFn: () => getPatient(patientId as string),
    enabled: Boolean(patientId),
  });

  const visitsQuery = useQuery({
    queryKey: ["patients", patientId, "visits"],
    queryFn: () => getPatientVisits(patientId as string),
    enabled: Boolean(patientId),
  });

  const deleteVisitMutation = useMutation({
    mutationFn: (visitId: number) => deletePatientVisit(patientId as string, visitId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits"],
      });
      await queryClient.invalidateQueries({
        queryKey: ["dashboard", "overview"],
      });
    },
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
          <div className="panel-heading panel-heading-between">
            <h3>Clinical Timeline</h3>
            <Link to={`/patients/${patient.id}/visits/new`} className="primary-link">
              New Visit
            </Link>
          </div>

          {visitsQuery.isLoading && <p className="empty-state">Loading visits...</p>}

          {!visitsQuery.isLoading && visitsQuery.data?.data.length === 0 && (
            <p className="empty-state">
              No visits yet. Create the first visit to start case-taking.
            </p>
          )}

          {visitsQuery.data && visitsQuery.data.data.length > 0 && (
            <div className="timeline-list">
              {visitsQuery.data.data.map((visit) => (
                <div className="timeline-item" key={visit.id}>
                  <div>
                    <Link
                      to={`/patients/${patient.id}/visits/${visit.id}`}
                      className="patient-name"
                    >
                      {visit.visit_date} — {visit.visit_type.replace("_", " ")}
                    </Link>
                    <p>{visit.chief_complaint || "No chief complaint added."}</p>
                    <span className={`status-pill ${visit.status}`}>{visit.status}</span>
                  </div>

                  <div className="table-actions">
                    <Link to={`/patients/${patient.id}/visits/${visit.id}/edit`}>
                      Edit
                    </Link>
                    <button
                      className="danger-button"
                      onClick={() => {
                        if (confirm("Delete this visit?")) {
                          deleteVisitMutation.mutate(visit.id);
                        }
                      }}
                      disabled={deleteVisitMutation.isPending}
                    >
                      Delete
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </article>
      </section>
    </div>
  );
}