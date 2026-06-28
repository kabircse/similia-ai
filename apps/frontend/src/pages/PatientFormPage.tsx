import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createPatient, getPatient, updatePatient } from "../lib/api";
import type { PatientInput } from "../lib/api";

const initialForm: PatientInput = {
  name: "",
  age_years: "",
  gender: "",
  phone: "",
  address: "",
  occupation: "",
  marital_status: "",
  emergency_contact: "",
  notes: "",
};

export function PatientFormPage() {
  const { patientId } = useParams();
  const isEdit = Boolean(patientId);
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [form, setForm] = useState<PatientInput>(initialForm);

  const patientQuery = useQuery({
    queryKey: ["patients", patientId],
    queryFn: () => getPatient(patientId as string),
    enabled: isEdit,
  });

  useEffect(() => {
    if (!patientQuery.data) return;

    setForm({
      name: patientQuery.data.name ?? "",
      age_years: patientQuery.data.age_years ?? "",
      gender: patientQuery.data.gender ?? "",
      phone: patientQuery.data.phone ?? "",
      address: patientQuery.data.address ?? "",
      occupation: patientQuery.data.occupation ?? "",
      marital_status: patientQuery.data.marital_status ?? "",
      emergency_contact: patientQuery.data.emergency_contact ?? "",
      notes: patientQuery.data.notes ?? "",
    });
  }, [patientQuery.data]);

  const saveMutation = useMutation({
    mutationFn: () =>
      isEdit
        ? updatePatient(patientId as string, form)
        : createPatient(form),
    onSuccess: async (patient) => {
      await queryClient.invalidateQueries({ queryKey: ["patients"] });
      await queryClient.invalidateQueries({ queryKey: ["dashboard", "overview"] });
      navigate(`/patients/${patient.id}`);
    },
  });

  function updateField(name: keyof PatientInput, value: string) {
    setForm((current) => ({
      ...current,
      [name]: name === "age_years" ? (value === "" ? "" : Number(value)) : value,
    }));
  }

  if (isEdit && patientQuery.isLoading) {
    return <div className="panel">Loading patient...</div>;
  }

  return (
    <div className="page-stack">
      <section className="page-header">
        <div>
          <p className="eyebrow">{isEdit ? "Edit Patient" : "New Patient"}</p>
          <h1>{isEdit ? "Update Patient Record" : "Create Patient Record"}</h1>
          <p>
            Store basic patient identity and contact details before creating
            visits and case-taking records.
          </p>
        </div>

        <Link to="/patients" className="secondary-link">
          Back to Patients
        </Link>
      </section>

      <form
        className="panel form-grid"
        onSubmit={(event) => {
            event.preventDefault();
            saveMutation.mutate();
        }}
        >
        <label>
          Name *
          <input
            value={form.name}
            required
            onChange={(event) => updateField("name", event.target.value)}
          />
        </label>

        <label>
          Age
          <input
            type="number"
            min="0"
            max="130"
            value={form.age_years}
            onChange={(event) => updateField("age_years", event.target.value)}
          />
        </label>

        <label>
          Gender
          <select
            value={form.gender}
            onChange={(event) => updateField("gender", event.target.value)}
          >
            <option value="">Select gender</option>
            <option value="female">Female</option>
            <option value="male">Male</option>
            <option value="other">Other</option>
            <option value="unknown">Unknown</option>
          </select>
        </label>

        <label>
          Phone
          <input
            value={form.phone}
            onChange={(event) => updateField("phone", event.target.value)}
          />
        </label>

        <label>
          Occupation
          <input
            value={form.occupation}
            onChange={(event) => updateField("occupation", event.target.value)}
          />
        </label>

        <label>
          Marital Status
          <select
            value={form.marital_status}
            onChange={(event) =>
              updateField("marital_status", event.target.value)
            }
          >
            <option value="">Select status</option>
            <option value="single">Single</option>
            <option value="married">Married</option>
            <option value="widowed">Widowed</option>
            <option value="divorced">Divorced</option>
            <option value="unknown">Unknown</option>
          </select>
        </label>

        <label className="full-field">
          Address
          <input
            value={form.address}
            onChange={(event) => updateField("address", event.target.value)}
          />
        </label>

        <label>
          Emergency Contact
          <input
            value={form.emergency_contact}
            onChange={(event) =>
              updateField("emergency_contact", event.target.value)
            }
          />
        </label>

        <label className="full-field">
          Notes
          <textarea
            rows={5}
            value={form.notes}
            onChange={(event) => updateField("notes", event.target.value)}
          />
        </label>

        {saveMutation.isError && (
          <div className="form-error full-field">
            Unable to save patient. Please check required fields.
          </div>
        )}

        <div className="form-actions full-field">
          <Link to="/patients" className="secondary-link">
            Cancel
          </Link>

          <button className="primary-button" disabled={saveMutation.isPending}>
            {saveMutation.isPending ? "Saving..." : "Save Patient"}
          </button>
        </div>
      </form>
    </div>
  );
}