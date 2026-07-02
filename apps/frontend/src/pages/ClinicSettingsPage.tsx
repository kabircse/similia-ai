import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Building2, Save } from "lucide-react";
import { getClinicSettings, updateClinicSettings } from "../lib/api";
import type { ClinicSetting, ClinicSettingInput } from "../lib/api";

function inputFromSettings(settings: ClinicSetting): ClinicSettingInput {
  return {
    clinic_name: settings.clinic_name ?? "",
    tagline: settings.tagline ?? "",
    doctor_display_name: settings.doctor_display_name ?? "",
    doctor_qualification: settings.doctor_qualification ?? "",
    phone: settings.phone ?? "",
    email: settings.email ?? "",
    website: settings.website ?? "",
    address: settings.address ?? "",
    logo_url: settings.logo_url ?? "",
    default_currency: settings.default_currency ?? "BDT",
    default_consultation_fee: settings.default_consultation_fee ?? "",
    default_followup_fee: settings.default_followup_fee ?? "",
    medicine_fee_included: Boolean(settings.medicine_fee_included),
    prescription_footer: settings.prescription_footer ?? "",
    case_sheet_footer: settings.case_sheet_footer ?? "",
    prescription_header: settings.prescription_header ?? "",
    prescription_disclaimer: settings.prescription_disclaimer ?? "",
    appointment_default_duration_minutes: settings.appointment_default_duration_minutes?.toString() ?? "",
    appointment_default_timezone: settings.appointment_default_timezone ?? "",
  };
}

function ClinicSettingsForm({ initialForm }: { initialForm: ClinicSettingInput }) {
  const queryClient = useQueryClient();
  const [form, setForm] = useState(initialForm);

  const updateMutation = useMutation({
    mutationFn: () => updateClinicSettings(form),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["clinic-settings"],
      });

      await queryClient.invalidateQueries({
        queryKey: ["activity-logs"],
      });
    },
  });

  function updateField<K extends keyof ClinicSettingInput>(
    key: K,
    value: ClinicSettingInput[K]
  ) {
    setForm((current) => ({
      ...current,
      [key]: value,
    }));
  }

  return (
    <form
      className="settings-form"
      onSubmit={(event) => {
        event.preventDefault();
        updateMutation.mutate();
      }}
    >
      <label>
        Clinic Name *
        <input
          required
          value={form.clinic_name}
          onChange={(event) => updateField("clinic_name", event.target.value)}
        />
      </label>

      <label>
        Tagline
        <input
          value={form.tagline}
          onChange={(event) => updateField("tagline", event.target.value)}
        />
      </label>

      <label>
        Doctor Display Name
        <input
          value={form.doctor_display_name}
          onChange={(event) =>
            updateField("doctor_display_name", event.target.value)
          }
        />
      </label>

      <label>
        Doctor Qualification
        <input
          value={form.doctor_qualification}
          onChange={(event) =>
            updateField("doctor_qualification", event.target.value)
          }
          placeholder="D.H.M.S, B.Sc"
        />
      </label>

      <label>
        Phone
        <input
          value={form.phone}
          onChange={(event) => updateField("phone", event.target.value)}
        />
      </label>

      <label>
        Email
        <input
          type="email"
          value={form.email}
          onChange={(event) => updateField("email", event.target.value)}
        />
      </label>

      <label>
        Website
        <input
          value={form.website}
          onChange={(event) => updateField("website", event.target.value)}
        />
      </label>

      <label>
        Logo URL
        <input
          value={form.logo_url}
          onChange={(event) => updateField("logo_url", event.target.value)}
          placeholder="https://..."
        />
      </label>

      <label className="full-field">
        Address
        <textarea
          rows={3}
          value={form.address}
          onChange={(event) => updateField("address", event.target.value)}
        />
      </label>

      <label>
        Default Currency
        <input
          value={form.default_currency}
          onChange={(event) => updateField("default_currency", event.target.value)}
        />
      </label>

      <label>
        Default First Consultation Fee
        <input
          type="number"
          min="0"
          value={form.default_consultation_fee}
          onChange={(event) =>
            updateField("default_consultation_fee", event.target.value)
          }
        />
      </label>

      <label>
        Default Follow-up Fee
        <input
          type="number"
          min="0"
          value={form.default_followup_fee}
          onChange={(event) =>
            updateField("default_followup_fee", event.target.value)
          }
        />
      </label>

      <label className="checkbox-label settings-checkbox">
        <input
          type="checkbox"
          checked={form.medicine_fee_included}
          onChange={(event) =>
            updateField("medicine_fee_included", event.target.checked)
          }
        />
        Medicine fee included
      </label>

      <label className="full-field">
        Prescription Footer
        <textarea
          rows={3}
          value={form.prescription_footer}
          onChange={(event) =>
            updateField("prescription_footer", event.target.value)
          }
        />
      </label>

      <label className="full-field">
        Case Sheet Footer
        <textarea
          rows={3}
          value={form.case_sheet_footer}
          onChange={(event) => updateField("case_sheet_footer", event.target.value)}
        />
      </label>

      <label className="full-field">
        Prescription Header
        <textarea
          rows={3}
          value={form.prescription_header}
          onChange={(event) => updateField("prescription_header", event.target.value)}
          placeholder="Clinic name, doctor name, qualifications"
        />
      </label>

      <label className="full-field">
        Prescription Disclaimer
        <textarea
          rows={3}
          value={form.prescription_disclaimer}
          onChange={(event) => updateField("prescription_disclaimer", event.target.value)}
          placeholder="Use this text to guide patients on follow-up and safety"
        />
      </label>

      <label>
        Default Appointment Duration (minutes)
        <input
          type="number"
          min="0"
          value={form.appointment_default_duration_minutes}
          onChange={(event) =>
            updateField("appointment_default_duration_minutes", event.target.value)
          }
        />
      </label>

      <label>
        Default Appointment Timezone
        <input
          value={form.appointment_default_timezone}
          onChange={(event) =>
            updateField("appointment_default_timezone", event.target.value)
          }
          placeholder="Asia/Dhaka"
        />
      </label>

      {updateMutation.isError && (
        <div className="form-error full-field">Unable to save clinic settings.</div>
      )}

      {updateMutation.isSuccess && (
        <div className="success-panel full-field">
          Clinic settings saved successfully.
        </div>
      )}

      <div className="form-actions full-field">
        <button
          type="submit"
          className="primary-button inline-button"
          disabled={updateMutation.isPending}
        >
          <Save size={16} />
          {updateMutation.isPending ? "Saving..." : "Save Settings"}
        </button>
      </div>
    </form>
  );
}

export function ClinicSettingsPage() {
  const settingsQuery = useQuery({
    queryKey: ["clinic-settings"],
    queryFn: getClinicSettings,
  });

  if (settingsQuery.isLoading) {
    return (
      <div className="panel">
        <p className="empty-state">Loading clinic settings...</p>
      </div>
    );
  }

  if (settingsQuery.isError || !settingsQuery.data) {
    return (
      <div className="panel error">
        <p>Unable to load clinic settings.</p>
      </div>
    );
  }

  return (
    <div className="page-stack">
      <section className="page-header">
        <div>
          <p className="eyebrow">Settings</p>
          <h1>Clinic Settings</h1>
          <p>
            Configure clinic identity, doctor display name, fees, and print
            document footer text.
          </p>
        </div>
      </section>

      <section className="panel">
        <div className="panel-heading">
          <div>
            <h3>
              <Building2 size={20} /> Clinic Profile
            </h3>
            <p className="panel-subtitle">
              These details appear on printed case sheets and prescriptions.
            </p>
          </div>
        </div>

        <ClinicSettingsForm
          key={`${settingsQuery.data.id}-${settingsQuery.data.updated_at}`}
          initialForm={inputFromSettings(settingsQuery.data)}
        />
      </section>
    </div>
  );
}
