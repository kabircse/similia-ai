<?php

namespace Tests\Feature;

use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentReminder;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use App\Services\Appointments\AppointmentReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicAppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_schedule_visit_appointment_with_reminders(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'name' => 'Appointment Patient',
        ]);
        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        $this->actingAs($doctor);

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/appointments", [
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
            'appointment_type' => 'follow_up',
            'scheduled_start_at' => now()->addDays(3)->toISOString(),
            'duration_minutes' => 30,
            'contact_method' => 'phone',
            'reminder_minutes_before' => [1440, 120],
        ])
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonCount(2, 'data.reminders');

        $this->assertDatabaseHas('clinic_appointments', [
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
            'doctor_id' => $doctor->id,
            'appointment_type' => 'follow_up',
            'status' => 'scheduled',
        ]);

        $this->assertDatabaseCount('clinic_appointment_reminders', 2);
    }

    public function test_doctor_can_update_appointment_status(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);
        $appointment = $this->makeAppointment($doctor, $patient);

        $this->actingAs($doctor);

        $this->patchJson("/api/appointments/{$appointment->id}/status", [
            'status' => 'confirmed',
            'doctor_note' => 'Patient confirmed.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('clinic_appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
            'doctor_note' => 'Patient confirmed.',
        ]);
    }

    public function test_due_appointment_reminder_creates_notification_and_is_marked_sent(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);
        $appointment = $this->makeAppointment($doctor, $patient, [
            'scheduled_start_at' => now()->addHour(),
            'scheduled_end_at' => now()->addMinutes(90),
        ]);

        $reminder = ClinicAppointmentReminder::create([
            'clinic_appointment_id' => $appointment->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'reminder_type' => 'doctor_task',
            'channel' => 'in_app',
            'status' => 'pending',
            'minutes_before' => 120,
            'due_at' => now()->subMinute(),
            'title' => 'Appointment reminder',
            'message' => 'Patient has appointment soon.',
        ]);

        $sent = app(AppointmentReminderService::class)->sendDueReminders();

        $this->assertSame(1, $sent);
        $this->assertDatabaseHas('clinic_appointment_reminders', [
            'id' => $reminder->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $doctor->id,
            'patient_id' => $patient->id,
            'category' => 'appointment',
            'title' => 'Appointment reminder',
        ]);
    }

    public function test_doctor_cannot_view_other_doctor_appointments(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $otherDoctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create(['doctor_id' => $otherDoctor->id]);

        $this->makeAppointment($otherDoctor, $patient, [
            'title' => 'Hidden appointment',
        ]);

        $this->actingAs($doctor);

        $this->getJson('/api/appointments')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_reminder_command_sends_due_reminders(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);
        $appointment = $this->makeAppointment($doctor, $patient);

        ClinicAppointmentReminder::create([
            'clinic_appointment_id' => $appointment->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'reminder_type' => 'doctor_task',
            'channel' => 'in_app',
            'status' => 'pending',
            'minutes_before' => 1440,
            'due_at' => now()->subMinute(),
            'title' => 'Appointment reminder',
            'message' => 'Patient has appointment soon.',
        ]);

        $this->artisan('appointments:send-reminders')
            ->expectsOutput('Sent 1 appointment reminder(s).')
            ->assertSuccessful();
    }

    private function makeAppointment(User $doctor, Patient $patient, array $overrides = []): ClinicAppointment
    {
        return ClinicAppointment::create(array_merge([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_type' => 'follow_up',
            'source' => 'manual',
            'status' => 'scheduled',
            'scheduled_start_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addMinutes(30),
            'timezone' => 'Asia/Dhaka',
            'title' => 'Follow-up',
            'contact_method' => 'phone',
            'send_reminders' => true,
            'reminder_minutes_before' => [1440],
        ], $overrides));
    }
}
