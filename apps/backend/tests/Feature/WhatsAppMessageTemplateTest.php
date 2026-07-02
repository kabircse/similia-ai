<?php

namespace Tests\Feature;

use App\Models\ClinicAppointment;
use App\Models\ClinicSetting;
use App\Models\Patient;
use App\Models\User;
use App\Models\WhatsAppMessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppMessageTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_list_active_templates(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        WhatsAppMessageTemplate::create([
            'title' => 'Active template',
            'category' => 'follow_up_reminder',
            'language' => 'en',
            'body' => 'Hello {{patient_name}}',
            'variables' => ['patient_name'],
            'is_active' => true,
        ]);
        WhatsAppMessageTemplate::create([
            'title' => 'Inactive template',
            'category' => 'follow_up_reminder',
            'language' => 'en',
            'body' => 'Hidden',
            'is_active' => false,
        ]);

        $this->actingAs($doctor);

        $this->getJson('/api/whatsapp/templates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Active template');
    }

    public function test_template_variables_are_replaced_and_phone_is_normalized(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'name' => 'WhatsApp Patient',
            'phone' => '01711-222333',
        ]);
        $appointment = ClinicAppointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_type' => 'follow_up',
            'source' => 'manual',
            'status' => 'scheduled',
            'scheduled_start_at' => now()->setDate(2026, 7, 5)->setTime(10, 30),
            'timezone' => 'Asia/Dhaka',
            'title' => 'Follow-up',
            'contact_method' => 'phone',
            'send_reminders' => true,
            'reminder_minutes_before' => [1440],
        ]);
        $template = WhatsAppMessageTemplate::create([
            'title' => 'Appointment template',
            'category' => 'appointment_reminder',
            'language' => 'en',
            'body' => 'Hello {{patient_name}}, appointment {{appointment_date}} {{appointment_time}}. {{custom_note}}',
            'variables' => ['patient_name', 'appointment_date', 'appointment_time', 'custom_note'],
            'is_active' => true,
        ]);

        $this->actingAs($doctor);

        $response = $this->postJson('/api/whatsapp/templates/render', [
            'template_id' => $template->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'variables' => [
                'custom_note' => 'Bring reports.',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.phone', '8801711222333')
            ->assertJsonPath('data.message', 'Hello WhatsApp Patient, appointment 2026-07-05 10:30 AM. Bring reports.');

        $this->assertStringStartsWith(
            'https://wa.me/8801711222333?text=',
            $response->json('data.whatsapp_url')
        );
    }

    public function test_render_uses_doctor_clinic_settings_for_branding_variables(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'name' => 'Branding Patient',
            'phone' => '01711-222333',
        ]);

        ClinicSetting::create([
            'doctor_id' => $doctor->id,
            'clinic_name' => 'Dr. Brand Clinic',
            'doctor_display_name' => 'Dr. Brand',
            'prescription_header' => 'Dr. Brand\nHomeopath',
            'prescription_disclaimer' => 'Follow-up advised',
        ]);

        $template = WhatsAppMessageTemplate::create([
            'title' => 'Branding template',
            'category' => 'general_notice',
            'language' => 'en',
            'body' => 'Hello {{patient_name}} from {{clinic_name}}. Dr: {{doctor_name}}. {{prescription_header}}. {{prescription_disclaimer}}',
            'variables' => ['patient_name', 'clinic_name', 'doctor_name', 'prescription_header', 'prescription_disclaimer'],
            'is_active' => true,
        ]);

        $this->actingAs($doctor);

        $this->postJson('/api/whatsapp/templates/render', [
            'template_id' => $template->id,
            'patient_id' => $patient->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.message', 'Hello Branding Patient from Dr. Brand Clinic. Dr: Dr. Brand. Dr. Brand\nHomeopath. Follow-up advised');
    }

    public function test_inactive_template_cannot_be_rendered(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);
        $template = WhatsAppMessageTemplate::create([
            'title' => 'Inactive',
            'category' => 'general_notice',
            'language' => 'en',
            'body' => 'Hello',
            'is_active' => false,
        ]);

        $this->actingAs($doctor);

        $this->postJson('/api/whatsapp/templates/render', [
            'template_id' => $template->id,
            'patient_id' => $patient->id,
        ])->assertNotFound();
    }

    public function test_doctor_cannot_render_another_doctors_private_template(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $otherDoctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create(['doctor_id' => $doctor->id]);
        $template = WhatsAppMessageTemplate::create([
            'doctor_id' => $otherDoctor->id,
            'title' => 'Private',
            'category' => 'general_notice',
            'language' => 'en',
            'body' => 'Hello {{patient_name}}',
            'is_active' => true,
        ]);

        $this->actingAs($doctor);

        $this->postJson('/api/whatsapp/templates/render', [
            'template_id' => $template->id,
            'patient_id' => $patient->id,
        ])->assertForbidden();
    }

    public function test_render_without_phone_returns_null_whatsapp_url(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'phone' => null,
        ]);
        $template = WhatsAppMessageTemplate::create([
            'title' => 'General',
            'category' => 'general_notice',
            'language' => 'en',
            'body' => 'Hello {{patient_name}}',
            'is_active' => true,
        ]);

        $this->actingAs($doctor);

        $this->postJson('/api/whatsapp/templates/render', [
            'template_id' => $template->id,
            'patient_id' => $patient->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.whatsapp_url', null);
    }
}
