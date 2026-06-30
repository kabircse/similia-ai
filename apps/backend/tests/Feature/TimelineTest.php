<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_timeline_returns_visit_items(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        PatientVisit::factory()->count(2)->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        $this->actingAs($doctor);

        $response = $this->getJson("/api/patients/{$patient->id}/timeline");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.patient.id', $patient->id);
    }
}
