import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  createPatientVisit,
  getPatient,
  getPatientVisit,
  updatePatientVisit,
} from "../lib/api";
import type { CaseSections, VisitInput } from "../lib/api";

const initialSections: CaseSections = {
  location: "",
  sensation: "",
  modalities: "",
  concomitants: "",
  mentals: "",
  generals: "",
  thermal_state: "",
  thirst: "",
  appetite: "",
  food_desires: "",
  food_aversions: "",
  sleep: "",
  dreams: "",
  stool: "",
  urine: "",
  menses: "",
  past_history: "",
  family_history: "",
  current_medicine: "",
  reports_note: "",
};

const sectionLabels: Array<[keyof CaseSections, string]> = [
  ["location", "Location"],
  ["sensation", "Sensation"],
  ["modalities", "Modalities / Better-Worse"],
  ["concomitants", "Concomitants"],
  ["mentals", "Mentals"],
  ["generals", "Generals"],
  ["thermal_state", "Thermal State"],
  ["thirst", "Thirst"],
  ["appetite", "Appetite"],
  ["food_desires", "Food Desires"],
  ["food_aversions", "Food Aversions"],
  ["sleep", "Sleep"],
  ["dreams", "Dreams"],
  ["stool", "Stool"],
  ["urine", "Urine"],
  ["menses", "Menses"],
  ["past_history", "Past History"],
  ["family_history", "Family History"],
  ["current_medicine", "Current Medicine"],
  ["reports_note", "Reports / Test Notes"],
];

function todayDate() {
  return new Date().toISOString().slice(0, 10);
}

const initialForm: VisitInput = {
  visit_date: todayDate(),
  visit_type: "initial",
  status: "draft",
  case_source: "manual",
  chief_complaint: "",
  raw_case_text: "",
  case_sections: initialSections,
  doctor_notes: "",
  next_follow_up_date: "",
};

export function VisitFormPage() {
  const { patientId, visitId } = useParams();
  const isEdit = Boolean(visitId);
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [form, setForm] = useState<VisitInput>(initialForm);

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

  useEffect(() => {
    if (!visitQuery.data) return;

    setForm({
      visit_date: visitQuery.data.visit_date,
      visit_type: visitQuery.data.visit_type,
      status: visitQuery.data.status,
      case_source: visitQuery.data.case_source,
      chief_complaint: visitQuery.data.chief_complaint ?? "",
      raw_case_text: visitQuery.data.raw_case_text ?? "",
      case_sections: {
        ...initialSections,
        ...visitQuery.data.case_sections,
      },
      doctor_notes: visitQuery.data.doctor_notes ?? "",
      next_follow_up_date: visitQuery.data.next_follow_up_date ?? "",
    });
  }, [visitQuery.data]);

  const saveMutation = useMutation({
    mutationFn: () =>
      isEdit
        ? updatePatientVisit(patientId as string, visitId as string, form)
        : createPatientVisit(patientId as string, form),
    onSuccess: async (visit) => {
      await queryClient.invalidateQueries({ queryKey: ["patients", patientId, "visits"] });
      await queryClient.invalidateQueries({ queryKey: ["patients", patientId, "timeline"] });
      await queryClient.invalidateQueries({ queryKey: ["dashboard", "overview"] });
      navigate(`/patients/${patientId}/visits/${visit.id}`);
    },
  });

  function updateField<K extends keyof VisitInput>(key: K, value: VisitInput[K]) {
    setForm((current) => ({
      ...current,
      [key]: value,
    }));
  }

  function updateSection(key: keyof CaseSections, value: string) {
    setForm((current) => ({
      ...current,
      case_sections: {
        ...current.case_sections,
        [key]: value,
      },
    }));
  }

  function handleSubmit(event: { preventDefault(): void }) {
    event.preventDefault();
    saveMutation.mutate();
  }

  if (patientQuery.isLoading || (isEdit && visitQuery.isLoading)) {
    return <div className="panel">Loading visit form...</div>;
  }

  return (
    <div className="page-stack">
      <section className="page-header">
        <div>
          <p className="eyebrow">{isEdit ? "Edit Visit" : "New Visit"}</p>
          <h1>
            {isEdit ? "Update Case Taking" : "Create Case Taking"}{" "}
            {patientQuery.data ? `for ${patientQuery.data.name}` : ""}
          </h1>
          <p>
            Save raw case notes and manual classical case-taking sections. AI
            structuring will be added in Issue #7.
          </p>
        </div>

        <Link to={`/patients/${patientId}`} className="secondary-link">
          Back to Patient
        </Link>
      </section>

      <form className="page-stack" onSubmit={handleSubmit}>
        <section className="panel form-grid">
          <label>
            Visit Date *
            <input
              type="date"
              value={form.visit_date}
              required
              onChange={(event) => updateField("visit_date", event.target.value)}
            />
          </label>

          <label>
            Visit Type
            <select
              value={form.visit_type}
              onChange={(event) =>
                updateField("visit_type", event.target.value as VisitInput["visit_type"])
              }
            >
              <option value="initial">Initial</option>
              <option value="follow_up">Follow-up</option>
            </select>
          </label>

          <label>
            Status
            <select
              value={form.status}
              onChange={(event) =>
                updateField("status", event.target.value as VisitInput["status"])
              }
            >
              <option value="draft">Draft</option>
              <option value="completed">Completed</option>
            </select>
          </label>

          <label>
            Case Source
            <select
              value={form.case_source}
              onChange={(event) =>
                updateField("case_source", event.target.value as VisitInput["case_source"])
              }
            >
              <option value="manual">Manual</option>
              <option value="raw">Raw Text</option>
              <option value="mixed">Mixed</option>
            </select>
          </label>

          <label className="full-field">
            Chief Complaint
            <textarea
              rows={3}
              value={form.chief_complaint}
              onChange={(event) => updateField("chief_complaint", event.target.value)}
              placeholder="Main complaint, duration, location, severity..."
            />
          </label>

          <label className="full-field">
            Raw Case Notes
            <textarea
              rows={6}
              value={form.raw_case_text}
              onChange={(event) => updateField("raw_case_text", event.target.value)}
              placeholder="Paste partial symptoms or unstructured case notes here. AI will structure this in Issue #7."
            />
          </label>
        </section>

        <section className="panel">
          <div className="panel-heading">
            <h3>Manual Classical Case-Taking Sections</h3>
          </div>

          <div className="case-section-grid">
            {sectionLabels.map(([key, label]) => (
              <label key={key}>
                {label}
                <textarea
                  rows={4}
                  value={form.case_sections[key]}
                  onChange={(event) => updateSection(key, event.target.value)}
                />
              </label>
            ))}
          </div>
        </section>

        <section className="panel form-grid">
          <label className="full-field">
            Doctor Notes
            <textarea
              rows={4}
              value={form.doctor_notes}
              onChange={(event) => updateField("doctor_notes", event.target.value)}
            />
          </label>

          <label>
            Next Follow-up Date
            <input
              type="date"
              value={form.next_follow_up_date}
              onChange={(event) => updateField("next_follow_up_date", event.target.value)}
            />
          </label>

          {saveMutation.isError && (
            <div className="form-error full-field">
              Unable to save visit. Please check required fields.
            </div>
          )}

          <div className="form-actions full-field">
            <Link to={`/patients/${patientId}`} className="secondary-link">
              Cancel
            </Link>

            <button className="primary-button" disabled={saveMutation.isPending}>
              {saveMutation.isPending ? "Saving..." : "Save Visit"}
            </button>
          </div>
        </section>
      </form>
    </div>
  );
}
