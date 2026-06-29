import { Link, useParams, useNavigate } from "react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  deletePatientVisit,
  getPatient,
  getPatientVisit,
  structurePatientVisit,
} from "../lib/api";
import { Brain } from "lucide-react";
import { VisitRubricsPanel } from "../components/rubrics/VisitRubricsPanel";
import { WeightedRepertorizationPanel } from "../components/repertorization/WeightedRepertorizationPanel";
import { CrossRepertorizationPanel } from "../components/repertorization/CrossRepertorizationPanel";
import { EliminativeRepertorizationPanel } from "../components/repertorization/EliminativeRepertorizationPanel";

export function VisitDetailPage() {
  const { patientId, visitId } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const patientQuery = useQuery({
    queryKey: ["patients", patientId],
    queryFn: () => getPatient(patientId as string),
    enabled: Boolean(patientId),
  });

  const visitQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId],
    queryFn: () => getPatientVisit(patientId as string, visitId as string),
    enabled: Boolean(patientId && visitId),
  });

  const deleteMutation = useMutation({
    mutationFn: () => deletePatientVisit(patientId as string, visitId as string),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["patients", patientId, "visits"] });
      await queryClient.invalidateQueries({ queryKey: ["dashboard", "overview"] });
      navigate(`/patients/${patientId}`);
    },
  });

  const structureMutation = useMutation({
    mutationFn: () => structurePatientVisit(patientId as string, visitId as string),
    onSuccess: async (updatedVisit) => {
      queryClient.setQueryData(
        ["patients", patientId, "visits", visitId],
        updatedVisit
      );
  
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits"],
      });
    },
  });

  if (patientQuery.isLoading || visitQuery.isLoading) {
    return <div className="panel">Loading visit...</div>;
  }

  if (!visitQuery.data) {
    return <div className="panel error">Unable to load visit.</div>;
  }

  const visit = visitQuery.data;
  const sections = visit.case_sections || {};
  const redFlags = visit.red_flags || [];
  const missingQuestions = visit.missing_questions || [];

  return (
    <div className="page-stack">
      <section className="page-header">
        <div>
          <p className="eyebrow">Case Taking</p>
          <h1>{patientQuery.data?.name} — {visit.visit_date}</h1>
          <p>
            Visit type: {visit.visit_type.replace("_", " ")} · Status: {visit.status}
          </p>
        </div>

        <div className="header-actions">
          <Link to={`/patients/${patientId}`} className="secondary-link">
            Back
          </Link>
          <button
            className="primary-button inline-button"
            onClick={() => structureMutation.mutate()}
            disabled={structureMutation.isPending}
          >
            <Brain size={16} />
            {structureMutation.isPending ? "Structuring..." : "Structure with AI"}
          </button>
          <Link
            to={`/patients/${patientId}/visits/${visit.id}/edit`}
            className="primary-link"
          >
            Edit Visit
          </Link>
          <button
            className="danger-button"
            onClick={() => {
              if (confirm("Delete this visit?")) {
                deleteMutation.mutate();
              }
            }}
            disabled={deleteMutation.isPending}
          >
            Delete
          </button>
        </div>
      </section>

      <section className="panel">
        <h3>Chief Complaint</h3>
        <p className="notes-text">{visit.chief_complaint || "Not added."}</p>
      </section>

      <section className="panel">
        <h3>Raw Case Notes</h3>
        <p className="notes-text">{visit.raw_case_text || "Not added."}</p>
      </section>

      {structureMutation.isError && (
        <section className="panel error">
          Unable to structure case. Make sure Laravel and FastAPI AI service are running.
        </section>
      )}

      {redFlags.length > 0 && (
          <section className="panel red-flag-panel">
            <h3>Red Flags</h3>
            <ul className="clinical-list">
              {redFlags.map((flag) => (
                <li key={flag}>{flag}</li>
              ))}
            </ul>
          </section>
        )}

        {missingQuestions.length > 0 && (
          <section className="panel question-panel">
            <h3>Missing Questions</h3>
            <ul className="clinical-list">
              {missingQuestions.map((question) => (
                <li key={question}>{question}</li>
              ))}
            </ul>
          </section>
        )}

      <section className="panel">
        <h3>Structured Case Sections</h3>

        <div className="case-detail-grid">
          {Object.entries(sections).map(([key, value]) => (
            <div className="case-detail-item" key={key}>
              <dt>{key.replaceAll("_", " ")}</dt>
              <dd>{value || "-"}</dd>
            </div>
          ))}
        </div>
      </section>

      {patientId && visitId && (
        <VisitRubricsPanel patientId={patientId} visitId={visitId} />
      )}
      
      {patientId && visitId && (
        <WeightedRepertorizationPanel patientId={patientId} visitId={visitId} />
      )}

      {patientId && visitId && (
        <CrossRepertorizationPanel patientId={patientId} visitId={visitId} />
      )}

      {patientId && visitId && (
        <EliminativeRepertorizationPanel patientId={patientId} visitId={visitId} />
      )}

      <section className="panel">
        <h3>Doctor Notes</h3>
        <p className="notes-text">{visit.doctor_notes || "No doctor notes."}</p>
      </section>

      <section className="panel">
        <h3>Next Steps</h3>
        <p className="empty-state">
          AI case structuring, rubric suggestion, repertorization, prescription,
          fee, and print modules will connect to this visit in upcoming issues.
        </p>
      </section>
    </div>
  );
}