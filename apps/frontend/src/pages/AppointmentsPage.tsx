import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CalendarDays, CheckCircle2, Clock, Phone } from "lucide-react";
import {
  getAppointmentSummary,
  getAppointments,
  updateAppointmentStatus,
} from "../lib/api";
import type { ClinicAppointment } from "../lib/api";
import { WhatsAppMessageComposer } from "../components/whatsapp/WhatsAppMessageComposer";

export function AppointmentsPage() {
  const [status, setStatus] = useState("");
  const summaryQuery = useQuery({
    queryKey: ["appointments-summary"],
    queryFn: getAppointmentSummary,
  });
  const appointmentsQuery = useQuery({
    queryKey: ["appointments", status],
    queryFn: () => getAppointments({ status: status || null }),
  });

  const appointments = appointmentsQuery.data?.data ?? [];

  return (
    <main className="page appointments-page">
      <div className="page-header">
        <div>
          <p className="eyebrow">Clinic Workflow</p>
          <h1>Appointments</h1>
          <p>Manage scheduled visits, follow-ups, and reminder tasks.</p>
        </div>
      </div>

      {summaryQuery.data && (
        <section className="appointment-kpis">
          <Kpi label="Today" value={summaryQuery.data.today_count} />
          <Kpi label="Upcoming" value={summaryQuery.data.upcoming_count} />
          <Kpi label="Overdue" value={summaryQuery.data.overdue_count} />
        </section>
      )}

      <section className="panel appointment-filter-panel">
        <label>
          Status
          <select
            className="method-select"
            value={status}
            onChange={(event) => setStatus(event.target.value)}
          >
            <option value="">All</option>
            <option value="scheduled">Scheduled</option>
            <option value="confirmed">Confirmed</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="no_show">No Show</option>
          </select>
        </label>
      </section>

      <section className="appointment-page-list">
        {appointments.map((appointment) => (
          <AppointmentRow appointment={appointment} key={appointment.id} />
        ))}

        {!appointmentsQuery.isLoading && appointments.length === 0 && (
          <p className="empty-state">No appointments found.</p>
        )}
      </section>
    </main>
  );
}

function Kpi({ label, value }: { label: string; value: number }) {
  return (
    <article className="appointment-kpi-card">
      <Clock size={18} />
      <span>{label}</span>
      <strong>{value}</strong>
    </article>
  );
}

function AppointmentRow({ appointment }: { appointment: ClinicAppointment }) {
  const queryClient = useQueryClient();
  const statusMutation = useMutation({
    mutationFn: (status: ClinicAppointment["status"]) =>
      updateAppointmentStatus(appointment.id, { status }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["appointments"] });
      await queryClient.invalidateQueries({ queryKey: ["appointments-summary"] });
    },
  });

  return (
    <article className={`appointment-row ${appointment.status}`}>
      <div className="appointment-row-main">
        <CalendarDays size={18} />

        <div>
          <strong>{appointment.title ?? "Appointment"}</strong>
          <p>
            {appointment.patient?.name ?? "Patient"} ·{" "}
            {formatDateTime(appointment.scheduled_start_at)}
          </p>
          <small>
            {labelize(appointment.appointment_type)} ·{" "}
            {labelize(appointment.contact_method)}
          </small>
        </div>
      </div>

      <div className="appointment-row-actions">
        {appointment.patient?.phone && (
          <a
            className="secondary-button inline-button"
            href={`tel:${appointment.patient.phone}`}
          >
            <Phone size={15} />
            Call
          </a>
        )}

        <button
          className="secondary-button inline-button"
          onClick={() => statusMutation.mutate("confirmed")}
          disabled={statusMutation.isPending}
          type="button"
        >
          Confirm
        </button>

        <button
          className="primary-button inline-button"
          onClick={() => statusMutation.mutate("completed")}
          disabled={statusMutation.isPending}
          type="button"
        >
          <CheckCircle2 size={15} />
          Complete
        </button>

        <button
          className="secondary-button inline-button"
          onClick={() => statusMutation.mutate("no_show")}
          disabled={statusMutation.isPending}
          type="button"
        >
          No Show
        </button>
      </div>

      <div className="appointment-whatsapp-slot">
        <WhatsAppMessageComposer
          compact
          patientId={appointment.patient_id}
          appointmentId={appointment.id}
        />
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
