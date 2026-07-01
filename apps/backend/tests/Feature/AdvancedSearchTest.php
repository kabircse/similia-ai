<?php

namespace Tests\Feature;

use App\Models\FollowUpAnalysisRun;
use App\Models\Patient;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_search_own_patients_visits_and_prescriptions(): void
    {
        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'name' => 'Abdul Karim',
            'phone' => '01711111111',
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'chief_complaint' => 'Chilly anxiety',
            'raw_case_text' => 'Patient is chilly, low thirst, desires sweets.',
        ]);

        PatientPrescription::create([
            'patient_visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'remedy_name' => 'Calcarea carbonica',
            'remedy_code' => 'calc',
            'potency' => '200C',
            'repetition' => 'single dose',
            'reason' => 'Chilly, low thirst, sweets desire.',
            'status' => 'final',
        ]);

        $this->actingAs($doctor);

        $this->getJson('/api/search/advanced?q=Calcarea&types[]=prescriptions')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'prescriptions')
            ->assertJsonPath('data.0.title', 'Calcarea carbonica 200C');

        $this->getJson('/api/search/advanced?q=Abdul&types[]=patients')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'patients')
            ->assertJsonPath('data.0.title', 'Abdul Karim');

        $this->getJson('/api/search/advanced?q=chilly&types[]=visits')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'visits');

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'search',
            'action' => 'advanced_search',
        ]);
    }

    public function test_doctor_cannot_search_other_doctors_records(): void
    {
        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $otherDoctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $otherPatient = Patient::factory()->create([
            'doctor_id' => $otherDoctor->id,
            'name' => 'Hidden Patient',
        ]);

        PatientVisit::factory()->create([
            'patient_id' => $otherPatient->id,
            'doctor_id' => $otherDoctor->id,
            'chief_complaint' => 'Secret complaint',
            'raw_case_text' => 'Secret symptom text.',
        ]);

        $this->actingAs($doctor);

        $this->getJson('/api/search/advanced?q=Secret&types[]=visits')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_search_finds_follow_up_ai_output(): void
    {
        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'name' => 'Outcome Patient',
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        FollowUpAnalysisRun::create([
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
            'doctor_id' => $doctor->id,
            'status' => 'completed',
            'response_level' => 'mixed',
            'progress_score' => 20,
            'analysis_summary' => 'Sleep improved but new headache appeared.',
            'new_symptoms' => ['new headache'],
            'red_flags' => [],
        ]);

        $this->actingAs($doctor);

        $this->getJson('/api/search/advanced?q=headache&types[]=follow_up_analyses')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'follow_up_analyses')
            ->assertJsonPath('data.0.patient_name', 'Outcome Patient');
    }

    public function test_search_requires_query(): void
    {
        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $this->actingAs($doctor);

        $this->getJson('/api/search/advanced?q=')
            ->assertStatus(422);
    }
}
