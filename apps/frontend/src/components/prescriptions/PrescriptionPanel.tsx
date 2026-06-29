import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ClipboardCheck, Trash2 } from "lucide-react";
import {
  deleteVisitPrescription,
  getRepertorizationRuns,
  getVisitPrescription,
  saveVisitPrescription,
  type PatientPrescription,
  type PrescriptionInput,
  type PrescriptionSourceMethod,
} from "../../lib/api";

type PrescriptionPanelProps = {
  patientId: string;
  visitId: string;
};

type RemedyOption = {
  resultId: number;
  method: string;
  remedyCode: string;
  remedyName: string;
  rank: number;
  score: number;
};

const initialForm: PrescriptionInput = {
  repertorization_result_id: null,
  source_method: "manual",
  remedy_code: "",
  remedy_name: "",
  potency: "",
  repetition: "",
  dose_instruction: "",
  reason: "",
  advice: "",
  food_lifestyle_note: "",
  follow_up_date: "",
  status: "draft",
};

function formFromPrescription(
  prescription: PatientPrescription | null
): PrescriptionInput {
  if (!prescription) {
    return initialForm;
  }

  return {
    repertorization_result_id: prescription.repertorization_result_id,
    source_method: prescription.source_method ?? "manual",
    remedy_code: prescription.remedy_code ?? "",
    remedy_name: prescription.remedy_name ?? "",
    potency: prescription.potency ?? "",
    repetition: prescription.repetition ?? "",
    dose_instruction: prescription.dose_instruction ?? "",
    reason: prescription.reason ?? "",
    advice: prescription.advice ?? "",
    food_lifestyle_note: prescription.food_lifestyle_note ?? "",
    follow_up_date: prescription.follow_up_date ?? "",
    status: prescription.status,
  };
}

function PrescriptionForm({
  patientId,
  visitId,
  prescription,
  remedyOptions,
}: PrescriptionPanelProps & {
  prescription: PatientPrescription | null;
  remedyOptions: RemedyOption[];
}) {
  const queryClient = useQueryClient();
  const [form, setForm] = useState<PrescriptionInput>(() =>
    formFromPrescription(prescription)
  );

  const saveMutation = useMutation({
    mutationFn: () => saveVisitPrescription(patientId, visitId, form),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits", visitId, "prescription"],
      });

      await queryClient.invalidateQueries({
        queryKey: ["dashboard", "overview"],
      });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteVisitPrescription(patientId, visitId),
    onSuccess: async () => {
      setForm(initialForm);

      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits", visitId, "prescription"],
      });

      await queryClient.invalidateQueries({
        queryKey: ["dashboard", "overview"],
      });
    },
  });

  function updateField<K extends keyof PrescriptionInput>(
    key: K,
    value: PrescriptionInput[K]
  ) {
    setForm((current) => ({
      ...current,
      [key]: value,
    }));
  }

  function handleUseRepertoryResult(value: string) {
    if (!value) {
      setForm((current) => ({
        ...current,
        repertorization_result_id: null,
        source_method: "manual",
      }));

      return;
    }

    const selected = remedyOptions.find(
      (option) => String(option.resultId) === value
    );

    if (!selected) {
      return;
    }

    setForm((current) => ({
      ...current,
      repertorization_result_id: selected.resultId,
      source_method: selected.method as PrescriptionSourceMethod,
      remedy_code: selected.remedyCode,
      remedy_name: selected.remedyName,
      reason:
        current.reason ||
        `Selected from ${selected.method} repertorization, rank #${selected.rank}, score ${selected.score}.`,
    }));
  }

  const hasPrescription = Boolean(prescription);

  return (
    <>
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>Doctor-Approved Prescription</h3>
          <p className="panel-subtitle">
            Save final remedy, potency, repetition, advice, and follow-up.
          </p>
        </div>

        {hasPrescription && (
          <button
            className="danger-button"
            onClick={() => {
              if (confirm("Delete this prescription?")) {
                deleteMutation.mutate();
              }
            }}
            disabled={deleteMutation.isPending}
          >
            <Trash2 size={15} />
            Delete
          </button>
        )}
      </div>

      <div className="safety-note">
        Repertorization and AI comparison are decision support only. Final
        remedy, potency, repetition, and instructions must be confirmed by the
        doctor.
      </div>

      <form
        className="prescription-form"
        onSubmit={(event) => {
          event.preventDefault();
          saveMutation.mutate();
        }}
      >
        <label className="full-field">
          Use Repertorization Result
          <select
            value={form.repertorization_result_id ?? ""}
            onChange={(event) => handleUseRepertoryResult(event.target.value)}
          >
            <option value="">Manual prescription</option>
            {remedyOptions.map((option) => (
              <option
                key={`${option.method}-${option.resultId}`}
                value={option.resultId}
              >
                {option.method} - #{option.rank} - {option.remedyName} - score{" "}
                {option.score}
              </option>
            ))}
          </select>
        </label>

        <label>
          Remedy Name *
          <input
            required
            value={form.remedy_name}
            onChange={(event) => updateField("remedy_name", event.target.value)}
            placeholder="Calcarea carbonica"
          />
        </label>

        <label>
          Remedy Code
          <input
            value={form.remedy_code}
            onChange={(event) => updateField("remedy_code", event.target.value)}
            placeholder="calc"
          />
        </label>

        <label>
          Potency *
          <input
            required
            value={form.potency}
            onChange={(event) => updateField("potency", event.target.value)}
            placeholder="30C / 200C / 1M"
          />
        </label>

        <label>
          Repetition
          <input
            value={form.repetition}
            onChange={(event) => updateField("repetition", event.target.value)}
            placeholder="Single dose / Once daily / As needed"
          />
        </label>

        <label className="full-field">
          Dose Instruction
          <textarea
            rows={3}
            value={form.dose_instruction}
            onChange={(event) =>
              updateField("dose_instruction", event.target.value)
            }
            placeholder="How the patient should take the medicine."
          />
        </label>

        <label className="full-field">
          Reason for Selection
          <textarea
            rows={3}
            value={form.reason}
            onChange={(event) => updateField("reason", event.target.value)}
            placeholder="Why this remedy was selected."
          />
        </label>

        <label className="full-field">
          Advice
          <textarea
            rows={3}
            value={form.advice}
            onChange={(event) => updateField("advice", event.target.value)}
          />
        </label>

        <label className="full-field">
          Food / Lifestyle Note
          <textarea
            rows={3}
            value={form.food_lifestyle_note}
            onChange={(event) =>
              updateField("food_lifestyle_note", event.target.value)
            }
          />
        </label>

        <label>
          Follow-up Date
          <input
            type="date"
            value={form.follow_up_date}
            onChange={(event) =>
              updateField("follow_up_date", event.target.value)
            }
          />
        </label>

        <label>
          Status
          <select
            value={form.status}
            onChange={(event) =>
              updateField(
                "status",
                event.target.value as PrescriptionInput["status"]
              )
            }
          >
            <option value="draft">Draft</option>
            <option value="final">Final</option>
          </select>
        </label>

        {saveMutation.isError && (
          <div className="form-error full-field">
            Unable to save prescription. Remedy name and potency are required.
          </div>
        )}

        {saveMutation.isSuccess && (
          <div className="success-panel full-field">
            Prescription saved successfully.
          </div>
        )}

        <div className="form-actions full-field">
          <button
            className="primary-button inline-button"
            disabled={saveMutation.isPending}
          >
            <ClipboardCheck size={16} />
            {saveMutation.isPending ? "Saving..." : "Save Prescription"}
          </button>
        </div>
      </form>
    </>
  );
}

export function PrescriptionPanel({
  patientId,
  visitId,
}: PrescriptionPanelProps) {
  const prescriptionQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "prescription"],
    queryFn: () => getVisitPrescription(patientId, visitId),
  });

  const runsQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "repertorization-runs"],
    queryFn: () => getRepertorizationRuns(patientId, visitId),
  });

  const remedyOptions = useMemo(() => {
    const runs = runsQuery.data?.data ?? [];

    return runs.flatMap((run) =>
      run.results.slice(0, 5).map((result) => ({
        resultId: result.id,
        method: run.method,
        remedyCode: result.remedy_code,
        remedyName: result.remedy_name,
        rank: result.rank,
        score: result.total_score,
      }))
    );
  }, [runsQuery.data]);

  if (prescriptionQuery.isLoading) {
    return <section className="panel">Loading prescription...</section>;
  }

  const prescription = prescriptionQuery.data ?? null;

  return (
    <section className="panel">
      <PrescriptionForm
        key={prescription?.id ?? "new"}
        patientId={patientId}
        visitId={visitId}
        prescription={prescription}
        remedyOptions={remedyOptions}
      />
    </section>
  );
}
