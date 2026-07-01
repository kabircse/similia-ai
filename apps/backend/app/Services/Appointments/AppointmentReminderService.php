<?php

namespace App\Services\Appointments;

use App\Models\ClinicAppointmentReminder;
use App\Services\Notifications\NotificationService;

class AppointmentReminderService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function sendDueReminders(int $limit = 100): int
    {
        $reminders = ClinicAppointmentReminder::query()
            ->with(['appointment.visit', 'appointment.patient', 'patient', 'doctor'])
            ->where('status', 'pending')
            ->where('due_at', '<=', now())
            ->whereHas('appointment', fn ($query) => $query->whereIn('status', ['scheduled', 'confirmed']))
            ->orderBy('due_at')
            ->limit($limit)
            ->get();

        $sent = 0;

        foreach ($reminders as $reminder) {
            if ($reminder->doctor) {
                $this->notifications->create(
                    user: $reminder->doctor,
                    title: $reminder->title ?: 'Appointment reminder',
                    message: $reminder->message ?: (($reminder->patient?->name ?? 'Patient').' has an upcoming appointment.'),
                    type: $reminder->appointment?->scheduled_start_at?->isToday() ? 'warning' : 'info',
                    category: 'appointment',
                    patient: $reminder->patient,
                    visit: $reminder->appointment?->visit,
                    actionUrl: $this->actionUrl($reminder),
                    metadata: [
                        'appointment_id' => $reminder->clinic_appointment_id,
                        'reminder_id' => $reminder->id,
                        'scheduled_start_at' => $reminder->appointment?->scheduled_start_at?->toISOString(),
                        'minutes_before' => $reminder->minutes_before,
                    ]
                );
            }

            $reminder->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $sent++;
        }

        return $sent;
    }

    private function actionUrl(ClinicAppointmentReminder $reminder): string
    {
        $appointment = $reminder->appointment;

        if ($appointment?->patient_visit_id) {
            return "/patients/{$appointment->patient_id}/visits/{$appointment->patient_visit_id}";
        }

        return '/appointments';
    }
}
