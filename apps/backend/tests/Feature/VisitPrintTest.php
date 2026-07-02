<?php

namespace Tests\Feature;

use App\Models\ClinicSetting;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_open_case_sheet_with_their_branding(): void
    {
        $doctor = User::factory()->create([
            'role' => 'doctor',
            'name' => 'Dr. Kabir Hossain',
        ]);
        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);
        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        ClinicSetting::create([
            'doctor_id' => $doctor->id,
            'clinic_name' => 'Kabir Homeopathic Center',
            'doctor_display_name' => 'Dr. Kabir Hossain',
            'doctor_qualification' => 'D.H.M.S',
            'email' => 'doctor@example.com',
            'default_currency' => 'BDT',
            'default_consultation_fee' => 3000,
            'default_followup_fee' => 2000,
            'medicine_fee_included' => true,
            'prescription_header' => 'Dr. Kabir Hossain Clinic',
            'prescription_disclaimer' => 'Doctor-only clinical document.',
            'case_sheet_footer' => 'Private case sheet footer.',
        ]);

        $this->actingAs($doctor);

        $this->getJson("/api/patients/{$patient->id}/visits/{$visit->id}/print/case-sheet")
            ->assertOk()
            ->assertJsonPath('data.clinic.name', 'Kabir Homeopathic Center')
            ->assertJsonPath('data.clinic.prescription_header', 'Dr. Kabir Hossain Clinic')
            ->assertJsonPath('data.clinic.prescription_disclaimer', 'Doctor-only clinical document.')
            ->assertJsonPath('data.clinic.case_sheet_footer', 'Private case sheet footer.')
            ->assertJsonPath('data.doctor.qualification', 'D.H.M.S');
    }

    public function test_admin_actions_keep_visit_outputs_scoped_to_patient_doctor(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $doctor = User::factory()->create([
            'role' => 'doctor',
            'name' => 'Dr. Owner',
            'email' => 'owner@example.com',
        ]);
        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        ClinicSetting::create([
            'doctor_id' => $doctor->id,
            'clinic_name' => 'Owner Clinic',
            'doctor_display_name' => 'Dr. Owner',
            'doctor_qualification' => 'BHMS',
            'email' => 'owner@example.com',
            'default_currency' => 'BDT',
            'default_consultation_fee' => 3000,
            'default_followup_fee' => 2000,
            'medicine_fee_included' => true,
            'prescription_header' => 'Owner Clinic Header',
            'prescription_disclaimer' => 'Owner doctor disclaimer.',
            'case_sheet_footer' => 'Owner case footer.',
        ]);

        $this->actingAs($admin);

        $visitResponse = $this->postJson("/api/patients/{$patient->id}/visits", [
            'visit_date' => now()->toDateString(),
            'visit_type' => 'initial',
            'status' => 'draft',
            'case_source' => 'manual',
            'chief_complaint' => 'Admin entered visit',
        ])
            ->assertCreated()
            ->assertJsonPath('data.doctor_id', $doctor->id);

        $visitId = $visitResponse->json('data.id');

        $this->putJson("/api/patients/{$patient->id}/visits/{$visitId}/prescription", [
            'remedy_name' => 'Nux vomica',
            'potency' => '30C',
            'status' => 'final',
        ])->assertOk();

        $this->putJson("/api/patients/{$patient->id}/visits/{$visitId}/fee", [
            'currency' => 'BDT',
            'consultation_fee' => 1200,
            'medicine_fee' => 300,
            'paid_amount' => 500,
        ])->assertOk();

        $this->assertDatabaseHas('patient_visits', [
            'id' => $visitId,
            'doctor_id' => $doctor->id,
        ]);
        $this->assertDatabaseHas('patient_prescriptions', [
            'patient_visit_id' => $visitId,
            'doctor_id' => $doctor->id,
            'remedy_name' => 'Nux vomica',
        ]);
        $this->assertDatabaseHas('patient_fees', [
            'patient_visit_id' => $visitId,
            'doctor_id' => $doctor->id,
        ]);

        $this->getJson("/api/patients/{$patient->id}/visits/{$visitId}/print/case-sheet")
            ->assertOk()
            ->assertJsonPath('data.clinic.name', 'Owner Clinic')
            ->assertJsonPath('data.clinic.prescription_header', 'Owner Clinic Header')
            ->assertJsonPath('data.doctor.id', $doctor->id)
            ->assertJsonPath('data.doctor.email', 'owner@example.com');
    }
}
