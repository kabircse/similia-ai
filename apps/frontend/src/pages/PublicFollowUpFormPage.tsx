import { useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { useParams } from "react-router";
import {
  getPublicFollowUpInvitation,
  submitPublicFollowUpForm,
  type PublicFollowUpSubmissionInput,
} from "../lib/api";

const initialForm: PublicFollowUpSubmissionInput = {
  overall_change: "unsure",
  medicine_taken: null,
  main_changes: "",
  current_symptoms: "",
  new_symptoms: "",
  aggravation_notes: "",
  other_medicines: "",
  general_notes: "",
  red_flag_notes: "",
  patient_questions: "",
  general_energy: "",
  sleep: "",
  appetite: "",
  mood: "",
  preferred_contact_time: "",
  consent_to_submit: false,
};

function formatDate(value: string | null) {
  if (!value) {
    return "-";
  }

  return new Intl.DateTimeFormat(undefined, { dateStyle: "medium" }).format(
    new Date(value)
  );
}

function formatLabel(value: string | null) {
  if (!value) {
    return "-";
  }

  return value.replaceAll("_", " ");
}

export function PublicFollowUpFormPage() {
  const { publicId, secret } = useParams();
  const [form, setForm] = useState<PublicFollowUpSubmissionInput>(initialForm);

  const invitationQuery = useQuery({
    queryKey: ["public-follow-up", publicId, secret],
    queryFn: () => getPublicFollowUpInvitation(publicId as string, secret as string),
    enabled: Boolean(publicId && secret),
    retry: false,
  });

  const submitMutation = useMutation({
    mutationFn: () =>
      submitPublicFollowUpForm(publicId as string, secret as string, form),
  });

  function updateField<K extends keyof PublicFollowUpSubmissionInput>(
    key: K,
    value: PublicFollowUpSubmissionInput[K]
  ) {
    setForm((current) => ({
      ...current,
      [key]: value,
    }));
  }

  if (invitationQuery.isLoading) {
    return (
      <main className="public-form-page">
        <section className="public-form-card">Loading follow-up form...</section>
      </main>
    );
  }

  if (invitationQuery.isError || !invitationQuery.data) {
    return (
      <main className="public-form-page">
        <section className="public-form-card">
          <h1>Follow-up link unavailable</h1>
          <p>The link may be expired, already used, or revoked by the clinic.</p>
        </section>
      </main>
    );
  }

  if (submitMutation.isSuccess) {
    return (
      <main className="public-form-page">
        <section className="public-form-card">
          <h1>Follow-up submitted</h1>
          <p>Your update has been sent to the clinic for doctor review.</p>
        </section>
      </main>
    );
  }

  const invitation = invitationQuery.data;

  return (
    <main className="public-form-page">
      <section className="public-form-card">
        <div>
          <p className="eyebrow">Secure Follow-up</p>
          <h1>Follow-up Form</h1>
          <p>
            {invitation.patient.name}
            {invitation.patient.age_years
              ? ` · ${invitation.patient.age_years} years`
              : ""}
            {invitation.patient.gender ? ` · ${invitation.patient.gender}` : ""}
          </p>
        </div>

        {invitation.message_to_patient && (
          <div className="public-message-box">{invitation.message_to_patient}</div>
        )}

        <div className="public-summary-grid">
          <div>
            <span>Expires</span>
            <strong>{formatDate(invitation.expires_at)}</strong>
          </div>
          <div>
            <span>Visit</span>
            <strong>{formatDate(invitation.visit?.visit_date ?? null)}</strong>
          </div>
          <div>
            <span>Complaint</span>
            <strong>{invitation.visit?.chief_complaint ?? "-"}</strong>
          </div>
        </div>

        {invitation.prescription && (
          <div className="public-prescription-box">
            <strong>
              {invitation.prescription.remedy_name}{" "}
              {invitation.prescription.potency}
            </strong>
            <span>{invitation.prescription.repetition ?? "As advised"}</span>
          </div>
        )}

        <form
          className="public-follow-up-form"
          onSubmit={(event) => {
            event.preventDefault();
            submitMutation.mutate();
          }}
        >
          <label>
            Overall change
            <select
              required
              value={form.overall_change}
              onChange={(event) =>
                updateField(
                  "overall_change",
                  event.target
                    .value as PublicFollowUpSubmissionInput["overall_change"]
                )
              }
            >
              <option value="improved">Improved</option>
              <option value="worse">Worse</option>
              <option value="same">Same</option>
              <option value="mixed">Mixed</option>
              <option value="unsure">Unsure</option>
            </select>
          </label>

          <label>
            Medicine taken
            <select
              value={
                form.medicine_taken === null
                  ? ""
                  : form.medicine_taken
                    ? "yes"
                    : "no"
              }
              onChange={(event) =>
                updateField(
                  "medicine_taken",
                  event.target.value === ""
                    ? null
                    : event.target.value === "yes"
                )
              }
            >
              <option value="">Select</option>
              <option value="yes">Yes</option>
              <option value="no">No</option>
            </select>
          </label>

          <TextArea
            label="What changed after treatment?"
            value={form.main_changes}
            onChange={(value) => updateField("main_changes", value)}
          />

          <TextArea
            label="What symptoms do you have now?"
            value={form.current_symptoms}
            onChange={(value) => updateField("current_symptoms", value)}
          />

          <TextArea
            label="Any new symptoms?"
            value={form.new_symptoms}
            onChange={(value) => updateField("new_symptoms", value)}
          />

          <TextArea
            label="Any aggravation or reaction?"
            value={form.aggravation_notes}
            onChange={(value) => updateField("aggravation_notes", value)}
          />

          <div className="public-form-grid">
            <SelectField
              label="Energy"
              value={form.general_energy}
              onChange={(value) => updateField("general_energy", value)}
            />
            <SelectField
              label="Sleep"
              value={form.sleep}
              onChange={(value) => updateField("sleep", value)}
            />
            <SelectField
              label="Appetite"
              value={form.appetite}
              onChange={(value) => updateField("appetite", value)}
            />
            <SelectField
              label="Mood"
              value={form.mood}
              onChange={(value) => updateField("mood", value)}
            />
          </div>

          <TextArea
            label="Other medicine or treatment taken"
            value={form.other_medicines}
            onChange={(value) => updateField("other_medicines", value)}
          />

          <TextArea
            label="Serious warning symptom"
            value={form.red_flag_notes}
            onChange={(value) => updateField("red_flag_notes", value)}
          />

          <TextArea
            label="Question for the doctor"
            value={form.patient_questions}
            onChange={(value) => updateField("patient_questions", value)}
          />

          <TextArea
            label="Other notes"
            value={form.general_notes}
            onChange={(value) => updateField("general_notes", value)}
          />

          <label>
            Preferred contact time
            <input
              value={form.preferred_contact_time}
              onChange={(event) =>
                updateField("preferred_contact_time", event.target.value)
              }
              placeholder="Evening after 7 PM"
            />
          </label>

          <label className="consent-check">
            <input
              type="checkbox"
              checked={form.consent_to_submit}
              onChange={(event) =>
                updateField("consent_to_submit", event.target.checked)
              }
              required
            />
            <span>
              I confirm this information is correct and I want to submit it to
              the clinic.
            </span>
          </label>

          {submitMutation.isError && (
            <div className="form-error">
              Submission failed. The link may be expired or already used.
            </div>
          )}

          <button
            className="primary-button"
            type="submit"
            disabled={submitMutation.isPending}
          >
            {submitMutation.isPending ? "Submitting..." : "Submit Follow-up"}
          </button>
        </form>
      </section>
    </main>
  );
}

function TextArea({
  label,
  value,
  onChange,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
}) {
  return (
    <label>
      {label}
      <textarea
        rows={4}
        value={value}
        onChange={(event) => onChange(event.target.value)}
      />
    </label>
  );
}

function SelectField({
  label,
  value,
  onChange,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
}) {
  return (
    <label>
      {label}
      <select value={value} onChange={(event) => onChange(event.target.value)}>
        <option value="">Select</option>
        <option value="better">Better</option>
        <option value="worse">Worse</option>
        <option value="same">{formatLabel("same")}</option>
      </select>
    </label>
  );
}
