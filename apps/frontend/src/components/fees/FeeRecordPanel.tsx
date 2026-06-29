import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ReceiptText, Trash2 } from "lucide-react";
import {
  deleteVisitFee,
  getVisitFee,
  saveVisitFee,
  type FeeInput,
  type PatientFee,
} from "../../lib/api";

type FeeRecordPanelProps = {
  patientId: string;
  visitId: string;
};

function todayDate() {
  return new Date().toISOString().slice(0, 10);
}

function createInitialForm(): FeeInput {
  return {
    currency: "BDT",
    consultation_fee: "",
    medicine_fee: "",
    discount_amount: "",
    paid_amount: "",
    payment_method: "",
    payment_date: todayDate(),
    note: "",
  };
}

function formFromFee(fee: PatientFee | null): FeeInput {
  if (!fee) {
    return createInitialForm();
  }

  return {
    currency: fee.currency ?? "BDT",
    consultation_fee: fee.consultation_fee ?? "",
    medicine_fee: fee.medicine_fee ?? "",
    discount_amount: fee.discount_amount ?? "",
    paid_amount: fee.paid_amount ?? "",
    payment_method: fee.payment_method ?? "",
    payment_date: fee.payment_date ?? "",
    note: fee.note ?? "",
  };
}

function toNumber(value: string) {
  if (value === "") {
    return 0;
  }

  const parsed = Number(value);

  return Number.isFinite(parsed) ? parsed : 0;
}

function calculatePaymentStatus(total: number, paid: number, due: number) {
  if (total <= 0 && paid <= 0) {
    return "paid";
  }

  if (paid <= 0) {
    return "unpaid";
  }

  if (due <= 0) {
    return "paid";
  }

  return "partial";
}

function FeeForm({
  patientId,
  visitId,
  fee,
}: FeeRecordPanelProps & {
  fee: PatientFee | null;
}) {
  const queryClient = useQueryClient();
  const [form, setForm] = useState<FeeInput>(() => formFromFee(fee));

  const preview = useMemo(() => {
    const consultation = toNumber(form.consultation_fee);
    const medicine = toNumber(form.medicine_fee);
    const discount = toNumber(form.discount_amount);
    const paid = toNumber(form.paid_amount);

    const subtotal = consultation + medicine;
    const total = Math.max(subtotal - discount, 0);
    const due = Math.max(total - paid, 0);

    return {
      subtotal,
      total,
      due,
      status: calculatePaymentStatus(total, paid, due),
    };
  }, [form]);

  const saveMutation = useMutation({
    mutationFn: () => saveVisitFee(patientId, visitId, form),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits", visitId, "fee"],
      });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteVisitFee(patientId, visitId),
    onSuccess: async () => {
      setForm(createInitialForm());

      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits", visitId, "fee"],
      });
    },
  });

  function updateField<K extends keyof FeeInput>(key: K, value: FeeInput[K]) {
    setForm((current) => ({
      ...current,
      [key]: value,
    }));
  }

  const hasFee = Boolean(fee);

  return (
    <>
      <div className="panel-heading panel-heading-between">
        <div>
          <h3>Fee Record</h3>
          <p className="panel-subtitle">
            Save consultation fee, payment, due amount, and method.
          </p>
        </div>

        {hasFee && (
          <button
            className="danger-button"
            onClick={() => {
              if (confirm("Delete this fee record?")) {
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

      <form
        className="fee-form"
        onSubmit={(event) => {
          event.preventDefault();
          saveMutation.mutate();
        }}
      >
        <label>
          Currency
          <input
            value={form.currency}
            onChange={(event) => updateField("currency", event.target.value)}
          />
        </label>

        <label>
          Consultation Fee
          <input
            type="number"
            min="0"
            step="1"
            value={form.consultation_fee}
            onChange={(event) =>
              updateField("consultation_fee", event.target.value)
            }
            placeholder="3000"
          />
        </label>

        <label>
          Medicine Fee
          <input
            type="number"
            min="0"
            step="1"
            value={form.medicine_fee}
            onChange={(event) => updateField("medicine_fee", event.target.value)}
            placeholder="0"
          />
        </label>

        <label>
          Discount
          <input
            type="number"
            min="0"
            step="1"
            value={form.discount_amount}
            onChange={(event) =>
              updateField("discount_amount", event.target.value)
            }
            placeholder="0"
          />
        </label>

        <label>
          Paid Amount
          <input
            type="number"
            min="0"
            step="1"
            value={form.paid_amount}
            onChange={(event) => updateField("paid_amount", event.target.value)}
            placeholder="3000"
          />
        </label>

        <label>
          Payment Method
          <select
            value={form.payment_method}
            onChange={(event) =>
              updateField("payment_method", event.target.value)
            }
          >
            <option value="">Select method</option>
            <option value="cash">Cash</option>
            <option value="bkash">bKash</option>
            <option value="nagad">Nagad</option>
            <option value="card">Card</option>
            <option value="bank">Bank</option>
            <option value="other">Other</option>
          </select>
        </label>

        <label>
          Payment Date
          <input
            type="date"
            value={form.payment_date}
            onChange={(event) => updateField("payment_date", event.target.value)}
          />
        </label>

        <label className="full-field">
          Note
          <textarea
            rows={3}
            value={form.note}
            onChange={(event) => updateField("note", event.target.value)}
            placeholder="Any fee or payment note..."
          />
        </label>

        <div className="fee-summary full-field">
          <div>
            <span>Subtotal</span>
            <strong>
              {form.currency} {preview.subtotal.toFixed(2)}
            </strong>
          </div>

          <div>
            <span>Total</span>
            <strong>
              {form.currency} {preview.total.toFixed(2)}
            </strong>
          </div>

          <div>
            <span>Due</span>
            <strong>
              {form.currency} {preview.due.toFixed(2)}
            </strong>
          </div>

          <div>
            <span>Status</span>
            <strong className={`fee-status ${preview.status}`}>
              {preview.status}
            </strong>
          </div>
        </div>

        {saveMutation.isError && (
          <div className="form-error full-field">
            Unable to save fee record. Please check the values.
          </div>
        )}

        {saveMutation.isSuccess && (
          <div className="success-panel full-field">
            Fee record saved successfully.
          </div>
        )}

        <div className="form-actions full-field">
          <button
            className="primary-button inline-button"
            disabled={saveMutation.isPending}
          >
            <ReceiptText size={16} />
            {saveMutation.isPending ? "Saving..." : "Save Fee Record"}
          </button>
        </div>
      </form>
    </>
  );
}

export function FeeRecordPanel({ patientId, visitId }: FeeRecordPanelProps) {
  const feeQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "fee"],
    queryFn: () => getVisitFee(patientId, visitId),
  });

  if (feeQuery.isLoading) {
    return <section className="panel">Loading fee record...</section>;
  }

  const fee = feeQuery.data ?? null;

  return (
    <section className="panel">
      <FeeForm
        key={fee?.id ?? "new"}
        patientId={patientId}
        visitId={visitId}
        fee={fee}
      />
    </section>
  );
}
