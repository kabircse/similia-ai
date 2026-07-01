<?php

namespace App\Services\Appointments;

use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentReminder;
use App\Models\Patient;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ClinicAppointmentService
{
    public function create(array $input, int $doctorId): ClinicAppointment
    {
        return DB::transaction(function () use ($input, $doctorId) {
            $patient = Patient::findOrFail($input['patient_id']);
            $visit = ! empty($input['patient_visit_id'])
                ? PatientVisit::findOrFail($input['patient_visit_id'])
                : null;
            $prescription = ! empty($input['prescription_id'])
                ? PatientPrescription::findOrFail($input['prescription_id'])
                : null;

            $this->ensureRelatedRecordsMatch($patient, $visit, $prescription);

            $start = Carbon::parse($input['scheduled_start_at']);
            $duration = (int) ($input['duration_minutes'] ?? 30);
            $reminders = $this->normalizeReminderMinutes(
                $input['reminder_minutes_before'] ?? [1440, 120]
            );
            $appointmentType = $input['appointment_type'] ?? 'follow_up';
            $source = $input['source'] ?? 'manual';

            $appointment = ClinicAppointment::create([
                'patient_id' => $patient->id,
                'patient_visit_id' => $visit?->id,
                'doctor_id' => $doctorId,
                'prescription_id' => $prescription?->id,
                'appointment_type' => $appointmentType,
                'source' => $source,
                'status' => 'scheduled',
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $start->copy()->addMinutes($duration),
                'timezone' => $input['timezone'] ?? 'Asia/Dhaka',
                'title' => $input['title'] ?? $this->defaultTitle($patient, $appointmentType),
                'reason' => $input['reason'] ?? null,
                'doctor_note' => $input['doctor_note'] ?? null,
                'patient_instruction' => $input['patient_instruction'] ?? null,
                'contact_method' => $input['contact_method'] ?? 'phone',
                'send_reminders' => $input['send_reminders'] ?? true,
                'reminder_minutes_before' => $reminders,
                'metadata' => [
                    'duration_minutes' => $duration,
                    'created_from' => $source,
                ],
            ]);

            $this->syncReminders($appointment);

            return $appointment->load(['patient', 'visit', 'prescription', 'reminders']);
        });
    }

    public function update(ClinicAppointment $appointment, array $input): ClinicAppointment
    {
        return DB::transaction(function () use ($appointment, $input) {
            $start = ! empty($input['scheduled_start_at'])
                ? Carbon::parse($input['scheduled_start_at'])
                : $appointment->scheduled_start_at;
            $duration = (int) ($input['duration_minutes'] ?? ($appointment->metadata['duration_minutes'] ?? 30));
            $reminders = array_key_exists('reminder_minutes_before', $input)
                ? $this->normalizeReminderMinutes($input['reminder_minutes_before'] ?? [])
                : ($appointment->reminder_minutes_before ?? [1440, 120]);

            $appointment->update([
                'appointment_type' => $input['appointment_type'] ?? $appointment->appointment_type,
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $start?->copy()->addMinutes($duration),
                'timezone' => $input['timezone'] ?? $appointment->timezone,
                'title' => $input['title'] ?? $appointment->title,
                'reason' => $input['reason'] ?? $appointment->reason,
                'doctor_note' => $input['doctor_note'] ?? $appointment->doctor_note,
                'patient_instruction' => $input['patient_instruction'] ?? $appointment->patient_instruction,
                'contact_method' => $input['contact_method'] ?? $appointment->contact_method,
                'send_reminders' => array_key_exists('send_reminders', $input)
                    ? (bool) $input['send_reminders']
                    : $appointment->send_reminders,
                'reminder_minutes_before' => $reminders,
                'metadata' => array_merge($appointment->metadata ?? [], [
                    'duration_minutes' => $duration,
                    'updated_at' => now()->toISOString(),
                ]),
            ]);

            $this->syncReminders($appointment->fresh());

            return $appointment->fresh(['patient', 'visit', 'prescription', 'reminders']);
        });
    }

    public function updateStatus(
        ClinicAppointment $appointment,
        string $status,
        ?string $doctorNote = null
    ): ClinicAppointment {
        return DB::transaction(function () use ($appointment, $status, $doctorNote) {
            $payload = [
                'status' => $status,
                'doctor_note' => $doctorNote ?? $appointment->doctor_note,
            ];

            if ($status === 'scheduled') {
                $payload['confirmed_at'] = null;
                $payload['completed_at'] = null;
                $payload['cancelled_at'] = null;
                $payload['no_show_at'] = null;
            }

            if ($status === 'confirmed') {
                $payload['confirmed_at'] = $appointment->confirmed_at ?? now();
                $payload['cancelled_at'] = null;
                $payload['no_show_at'] = null;
            }

            if ($status === 'completed') {
                $payload['completed_at'] = now();
                $this->cancelPendingReminders($appointment);
            }

            if ($status === 'cancelled') {
                $payload['cancelled_at'] = now();
                $this->cancelPendingReminders($appointment);
            }

            if ($status === 'no_show') {
                $payload['no_show_at'] = now();
                $this->cancelPendingReminders($appointment);
            }

            $appointment->update($payload);

            return $appointment->fresh(['patient', 'visit', 'prescription', 'reminders']);
        });
    }

    public function syncReminders(ClinicAppointment $appointment): void
    {
        if (! $appointment->send_reminders || ! in_array($appointment->status, ['scheduled', 'confirmed'], true)) {
            $this->cancelPendingReminders($appointment);

            return;
        }

        $minutesList = $this->normalizeReminderMinutes(
            $appointment->reminder_minutes_before ?? [1440, 120]
        );

        if ($minutesList === []) {
            $this->cancelPendingReminders($appointment);

            return;
        }

        foreach ($minutesList as $minutes) {
            $dueAt = $appointment->scheduled_start_at->copy()->subMinutes($minutes);

            ClinicAppointmentReminder::updateOrCreate(
                [
                    'clinic_appointment_id' => $appointment->id,
                    'reminder_type' => 'doctor_task',
                    'channel' => 'in_app',
                    'minutes_before' => $minutes,
                ],
                [
                    'doctor_id' => $appointment->doctor_id,
                    'patient_id' => $appointment->patient_id,
                    'status' => 'pending',
                    'due_at' => $dueAt,
                    'title' => 'Appointment reminder',
                    'message' => $this->reminderMessage($appointment, $minutes),
                    'metadata' => [
                        'appointment_type' => $appointment->appointment_type,
                        'contact_method' => $appointment->contact_method,
                    ],
                ]
            );
        }

        ClinicAppointmentReminder::query()
            ->where('clinic_appointment_id', $appointment->id)
            ->where('status', 'pending')
            ->whereNotIn('minutes_before', $minutesList)
            ->update(['status' => 'cancelled']);
    }

    public function cancelPendingReminders(ClinicAppointment $appointment): void
    {
        ClinicAppointmentReminder::query()
            ->where('clinic_appointment_id', $appointment->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    private function ensureRelatedRecordsMatch(
        Patient $patient,
        ?PatientVisit $visit,
        ?PatientPrescription $prescription
    ): void {
        if ($visit) {
            abort_unless($visit->patient_id === $patient->id, 422, 'Visit does not belong to patient.');
            abort_unless($visit->doctor_id === $patient->doctor_id, 422, 'Visit does not belong to patient doctor.');
        }

        if ($prescription) {
            abort_unless($prescription->patient_id === $patient->id, 422, 'Prescription does not belong to patient.');
            abort_unless($prescription->doctor_id === $patient->doctor_id, 422, 'Prescription does not belong to patient doctor.');
        }
    }

    private function normalizeReminderMinutes(array $minutes): array
    {
        return collect($minutes)
            ->map(fn ($item) => (int) $item)
            ->filter(fn ($item) => $item >= 5 && $item <= 10080)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    private function defaultTitle(Patient $patient, string $type): string
    {
        return match ($type) {
            'initial' => 'Initial consultation: '.$patient->name,
            'phone_follow_up' => 'Phone follow-up: '.$patient->name,
            'portal_review' => 'Portal review: '.$patient->name,
            'medicine_pickup' => 'Medicine pickup: '.$patient->name,
            default => 'Follow-up: '.$patient->name,
        };
    }

    private function reminderMessage(ClinicAppointment $appointment, int $minutes): string
    {
        $appointment->loadMissing('patient');
        $patientName = $appointment->patient?->name ?? 'Patient';
        $time = $appointment->scheduled_start_at?->format('Y-m-d h:i A');
        $when = match (true) {
            $minutes >= 1440 => round($minutes / 1440).' day(s)',
            $minutes >= 60 => round($minutes / 60).' hour(s)',
            default => $minutes.' minute(s)',
        };

        return "{$patientName} has an appointment at {$time}. Reminder: {$when} before appointment.";
    }
}
