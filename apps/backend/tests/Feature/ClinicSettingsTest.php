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
        ])
            ->assertOk()
            ->assertJsonPath('data.clinic_name', 'Kabir Homeopathic Center')
            ->assertJsonPath('data.default_currency', 'BDT');
    }
}
