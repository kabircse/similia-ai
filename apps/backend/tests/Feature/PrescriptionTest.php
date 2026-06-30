<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_save_visit_prescription(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        $this->actingAs($doctor);

        $response = $this->putJson("/api/patients/{$patient->id}/visits/{$visit->id}/prescription", [
            'source_method' => 'manual',
            'remedy_code' => 'calc',
            'remedy_name' => 'Calcarea carbonica',
            'potency' => '200C',
            'repetition' => 'Single dose',
            'dose_instruction' => 'Take one dose only.',
            'status' => 'final',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.remedy_name', 'Calcarea carbonica')
            ->assertJsonPath('data.potency', '200C')
            ->assertJsonPath('data.status', 'final');

        $this->assertDatabaseHas('patient_prescriptions', [
            'patient_visit_id' => $visit->id,
            'remedy_name' => 'Calcarea carbonica',
            'potency' => '200C',
        ]);
    }
}
