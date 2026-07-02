import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CalendarClock, CheckCircle2, XCircle } from "lucide-react";
import {
  createVisitAppointment,
  getClinicSettings,
  getVisitAppointments,
  updateAppointmentStatus,
} from "../../lib/api";
import type { ClinicAppointment } from "../../lib/api";

type VisitAppointmentPanelProps = {
  patientId: string | number;
  visitId: string | number;
};

export function VisitAppointmentPanel({
  patientId,
  visitId,
}: VisitAppointmentPanelProps) {
  const queryClient = useQueryClient();
  const [scheduledStartAt, setScheduledStartAt] = useState("");
  const [durationMinutes, setDurationMinutes] = useState(30);
  const [appointmentType, setAppointmentType] =
    useState<ClinicAppointment["appointment_type"]>("follow_up");
  const [contactMethod, setContactMethod] =
    useState<ClinicAppointment["contact_method"]>("phone");
  const [reason, setReason] = useState("");

  const appointmentsQuery = useQuery({
    queryKey: ["patients", patientId, "visits", visitId, "appointments"],
    queryFn: () => getVisitAppointments(patientId, visitId),
  });

  const clinicSettingsQuery = useQuery({
    queryKey: ["clinic-settings"],
    queryFn: getClinicSettings,
  });

  useEffect(() => {
    if (clinicSettingsQuery.data?.appointment_default_duration_minutes != null) {
      setDurationMinutes(clinicSettingsQuery.data.appointment_default_duration_minutes);
    }
  }, [clinicSettingsQuery.data]);

  const createMutation = useMutation({
    mutationFn: () =>
      createVisitAppointment(patientId, visitId, {
        patient_id: Number(patientId),
        patient_visit_id: Number(visitId),
        appointment_type: appointmentType,
        source: "manual",
        scheduled_start_at: scheduledStartAt,
        duration_minutes: durationMinutes,
        timezone: clinicSettingsQuery.data?.appointment_default_timezone ?? "Asia/Dhaka",
        contact_method: contactMethod,
        reason: reason || null,
        send_reminders: true,
        reminder_minutes_before: [1440, 120],
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits", visitId, "appointments"],
      });
      await queryClient.invalidateQueries({ queryKey: ["appointments"] });
      await queryClient.invalidateQueries({ queryKey: ["appointments-summary"] });
      setScheduledStartAt("");
      setReason("");
    },
  });

  const appointments = appointmentsQuery.data?.data ?? [];

  return (
    <section className="panel visit-appointment-panel">
      <div className="panel-heading">
        <div>
          <h3>
            <CalendarClock size={20} /> Appointment & Follow-up Reminder
          </h3>
          <p className="panel-subtitle">
            Schedule follow-up and create doctor reminder notifications.
          </p>
        </div>
      </div>

      <div className="appointment-form-grid">
        <label>
          Date & Time
          <input
            type="datetime-local"
            value={scheduledStartAt}
            onChange={(event) => setScheduledStartAt(event.target.value)}
          />
        </label>

        <label>
          Duration
          <select
            className="method-select"
            value={durationMinutes}
            onChange={(event) => setDurationMinutes(Number(event.target.value))}
          >
            <option value={15}>15 minutes</option>
            <option value={30}>30 minutes</option>
            <option value={45}>45 minutes</option>
            <option value={60}>60 minutes</option>
          </select>
        </label>

        <label>
          Type
          <select
            className="method-select"
            value={appointmentType}
            onChange={(event) =>
              setAppointmentType(
                event.target.value as ClinicAppointment["appointment_type"]
              )
            }
          >
            <option value="follow_up">Follow-up</option>
            <option value="phone_follow_up">Phone follow-up</option>
            <option value="portal_review">Portal review</option>
            <option value="medicine_pickup">Medicine pickup</option>
            <option value="other">Other</option>
          </select>
        </label>

        <label>
          Contact
          <select
            className="method-select"
            value={contactMethod}
            onChange={(event) =>
              setContactMethod(
                event.target.value as ClinicAppointment["contact_method"]
              )
            }
          >
            <option value="phone">Phone</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="in_person">In person</option>
            <option value="sms">SMS</option>
            <option value="email">Email</option>
          </select>
        </label>
      </div>

      <label className="appointment-reason-field">
        Reason / Note
        <textarea
          rows={2}
          value={reason}
          onChange={(event) => setReason(event.target.value)}
          placeholder="Example: Follow-up after medicine response"
        />
      </label>

      <div className="inline-actions">
        <button
          className="primary-button inline-button"
          onClick={() => createMutation.mutate()}
          disabled={createMutation.isPending || !scheduledStartAt}
          type="button"
        >
          <CalendarClock size={16} />
          {createMutation.isPending ? "Scheduling..." : "Schedule Appointment"}
        </button>
      </div>

      {createMutation.isError && (
        <div className="form-error">Unable to schedule appointment.</div>
      )}

      <div className="appointment-list">
        {appointments.map((appointment) => (
          <AppointmentCard
            appointment={appointment}
            patientId={patientId}
            visitId={visitId}
            key={appointment.id}
          />
        ))}

        {!appointmentsQuery.isLoading && appointments.length === 0 && (
          <p className="empty-state">No appointment scheduled for this visit.</p>
        )}
      </div>
    </section>
  );
}

function AppointmentCard({
  appointment,
  patientId,
  visitId,
}: {
  appointment: ClinicAppointment;
  patientId: string | number;
  visitId: string | number;
}) {
  const queryClient = useQueryClient();

  const statusMutation = useMutation({
    mutationFn: (status: ClinicAppointment["status"]) =>
      updateAppointmentStatus(appointment.id, { status }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["patients", patientId, "visits", visitId, "appointments"],
      });
      await queryClient.invalidateQueries({ queryKey: ["appointments"] });
      await queryClient.invalidateQueries({ queryKey: ["appointments-summary"] });
    },
  });

  return (
    <article className={`appointment-card ${appointment.status}`}>
      <div>
        <strong>{appointment.title ?? "Appointment"}</strong>
        <p>
          {formatDateTime(appointment.scheduled_start_at)} ·{" "}
          {labelize(appointment.appointment_type)} ·{" "}
          {labelize(appointment.contact_method)}
        </p>
        {appointment.reason && <p>{appointment.reason}</p>}
      </div>

      <div className="appointment-status-actions">
        <span>{labelize(appointment.status)}</span>

        <button
          className="secondary-button inline-button"
          onClick={() => statusMutation.mutate("confirmed")}
          disabled={statusMutation.isPending}
          type="button"
        >
          <CheckCircle2 size={15} />
          Confirm
        </button>

        <button
          className="primary-button inline-button"
          onClick={() => statusMutation.mutate("completed")}
          disabled={statusMutation.isPending}
          type="button"
        >
          Complete
        </button>

        <button
          className="secondary-button inline-button"
          onClick={() => statusMutation.mutate("cancelled")}
          disabled={statusMutation.isPending}
          type="button"
        >
          <XCircle size={15} />
          Cancel
        </button>
      </div>
    </article>
  );
}

function formatDateTime(value: string | null) {
  return value ? new Date(value).toLocaleString() : "No time";
}

function labelize(value: string) {
  return value.replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase());
}
