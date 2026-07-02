<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_view_and_update_clinic_settings(): void
    {
        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $this->actingAs($doctor);

        $this->getJson('/api/clinic-settings')
            ->assertOk()
            ->assertJsonPath('data.doctor_id', $doctor->id);

        $this->putJson('/api/clinic-settings', [
            'clinic_name' => 'Kabir Homeopathic Center',
            'tagline' => 'Classical Homeopathy Clinic',
            'doctor_display_name' => 'Dr. Kabir Hossain',
            'doctor_qualification' => 'D.H.M.S',
            'phone' => '01700000000',
            'email' => 'doctor@example.com',
            'default_currency' => 'BDT',
            'default_consultation_fee' => 3000,
            'default_followup_fee' => 2000,
            'medicine_fee_included' => true,
            'prescription_header' => 'Dr. Kabir Hossain\nD.H.M.S',
            'prescription_disclaimer' => 'Please adhere to the prescribed instructions.',
            'appointment_default_duration_minutes' => 45,
            'appointment_default_timezone' => 'Asia/Dhaka',
        ])
            ->assertOk()
            ->assertJsonPath('data.clinic_name', 'Kabir Homeopathic Center')
            ->assertJsonPath('data.default_currency', 'BDT')
            ->assertJsonPath('data.prescription_header', 'Dr. Kabir Hossain\nD.H.M.S')
            ->assertJsonPath('data.appointment_default_duration_minutes', 45);
    }

    public function test_admin_can_view_and_update_another_doctors_clinic_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $this->actingAs($admin);

        $this->getJson('/api/clinic-settings/' . $doctor->id)
            ->assertOk()
            ->assertJsonPath('data.doctor_id', $doctor->id);

        $this->putJson('/api/clinic-settings/' . $doctor->id, [
            'clinic_name' => 'Doctor Two Center',
            'prescription_header' => 'Dr. Two\nHomeopath',
        ])
            ->assertOk()
            ->assertJsonPath('data.clinic_name', 'Doctor Two Center')
            ->assertJsonPath('data.prescription_header', 'Dr. Two\nHomeopath');
    }
}
