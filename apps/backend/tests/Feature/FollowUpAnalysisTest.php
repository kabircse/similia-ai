<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FollowUpAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_generate_follow_up_analysis(): void
    {
        Http::fake([
            '*/follow-up/analyze' => Http::response([
                'response_level' => 'improved',
                'progress_score' => 45,
                'analysis_summary' => 'Follow-up response appears improved.',
                'remedy_response_assessment' => 'Response after Calcarea carbonica 200C appears improved.',
                'improvement_points' => [
                    'Sleep improved',
                    'Anxiety reduced',
                ],
                'worsening_points' => [],
                'unchanged_points' => [],
                'new_symptoms' => [],
                'old_symptoms_returned' => [],
                'possible_aggravation_signs' => [],
                'red_flags' => [],
                'suggested_follow_up_questions' => [
                    'Was there any initial aggravation?',
                ],
                'doctor_review_points' => [
                    'Review general wellbeing.',
                ],
                'recommended_next_steps' => [
                    'Do not repeat automatically.',
                ],
                'progress_items' => [
                    [
                        'category' => 'general',
                        'symptom' => 'Sleep improved',
                        'change_status' => 'improved',
                        'previous_intensity' => null,
                        'current_intensity' => null,
                        'change_score' => 20,
                        'evidence' => 'Sleep improved',
                        'metadata' => [],
                    ],
                ],
                'safety_note' => 'Doctor-facing decision support only.',
            ], 200),
        ]);

        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $previousVisit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'visit_date' => now()->subMonth(),
            'visit_type' => 'initial',
            'chief_complaint' => 'Anxiety and poor sleep',
            'raw_case_text' => 'Chilly, anxious, poor sleep.',
        ]);

        $prescription = PatientPrescription::create([
            'patient_visit_id' => $previousVisit->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'source_method' => 'manual',
            'remedy_name' => 'Calcarea carbonica',
            'remedy_code' => 'calc',
            'potency' => '200C',
            'repetition' => 'single dose',
            'status' => 'final',
        ]);

        $currentVisit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'visit_date' => now(),
            'visit_type' => 'follow_up',
            'chief_complaint' => 'Follow-up',
            'raw_case_text' => 'Sleep improved. Anxiety reduced.',
        ]);

        $this->actingAs($doctor);

        $this->postJson("/api/patients/{$patient->id}/visits/{$currentVisit->id}/follow-up-analyses/generate", [
            'previous_visit_id' => $previousVisit->id,
            'prescription_id' => $prescription->id,
            'response_language' => 'hi-IN',
        ])
            ->assertCreated()
            ->assertJsonPath('data.response_level', 'improved')
            ->assertJsonPath('data.progress_items.0.symptom', 'Sleep improved');

        $this->assertDatabaseHas('follow_up_analysis_runs', [
            'patient_visit_id' => $currentVisit->id,
            'previous_visit_id' => $previousVisit->id,
            'prescription_id' => $prescription->id,
            'response_level' => 'improved',
        ]);

        $this->assertDatabaseHas('follow_up_progress_items', [
            'patient_visit_id' => $currentVisit->id,
            'symptom' => 'Sleep improved',
            'change_status' => 'improved',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'patient_visit_id' => $currentVisit->id,
            'category' => 'follow_up',
            'action' => 'generated_follow_up_analysis',
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/follow-up/analyze')
            && $request->data()['response_language'] === 'hi-IN');
    }

    public function test_follow_up_analysis_requires_previous_visit(): void
    {
        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $currentVisit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'visit_type' => 'follow_up',
        ]);

        $this->actingAs($doctor);

        $this->postJson("/api/patients/{$patient->id}/visits/{$currentVisit->id}/follow-up-analyses/generate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'No previous visit found for follow-up comparison.');
    }
}
