<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrescriptionReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_generate_prescription_review(): void
    {
        Http::fake([
            '*/prescription/review' => Http::response([
                'review_status' => 'needs_doctor_review',
                'safety_score' => 75,
                'review_summary' => 'Safety checklist generated for Calcarea carbonica prescription.',
                'decision_guidance' => 'Confirm all required checklist items.',
                'risk_summary' => 'No blocking risk detected.',
                'red_flags' => [],
                'missing_information' => [],
                'doctor_review_points' => [
                    'Does the current totality support the remedy?',
                ],
                'recommended_actions' => [
                    'Confirm required checklist items.',
                ],
                'checks' => [
                    [
                        'check_key' => 'red_flags_reviewed',
                        'category' => 'safety',
                        'severity' => 'important',
                        'status' => 'passed',
                        'is_required' => true,
                        'is_blocking' => false,
                        'title' => 'Red flags reviewed',
                        'description' => 'Check red flags.',
                        'ai_assessment' => 'No red flags detected.',
                        'evidence' => [],
                        'metadata' => [],
                    ],
                    [
                        'check_key' => 'remedy_evidence_reviewed',
                        'category' => 'remedy',
                        'severity' => 'important',
                        'status' => 'pending',
                        'is_required' => true,
                        'is_blocking' => false,
                        'title' => 'Remedy evidence reviewed by doctor',
                        'description' => 'Confirm remedy.',
                        'ai_assessment' => 'Doctor review required.',
                        'evidence' => [],
                        'metadata' => [],
                    ],
                ],
                'safety_note' => 'Doctor-facing safety checklist only.',
            ], 200),
        ]);

        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'chief_complaint' => 'Chilly anxiety',
            'raw_case_text' => 'Chilly, low thirst, desire sweets.',
        ]);

        $prescription = PatientPrescription::create([
            'patient_visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'remedy_name' => 'Calcarea carbonica',
            'remedy_code' => 'calc',
            'potency' => '200C',
            'repetition' => 'single dose',
            'dose_instruction' => 'Take one dose at night.',
            'advice' => 'Report any aggravation.',
            'follow_up_date' => now()->addMonth(),
            'status' => 'draft',
        ]);

        $this->actingAs($doctor);

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/prescription-reviews/generate", [
            'prescription_id' => $prescription->id,
            'response_language' => 'en-US',
        ])
            ->assertCreated()
            ->assertJsonPath('data.review_status', 'needs_doctor_review')
            ->assertJsonPath('data.checks.0.check_key', 'red_flags_reviewed');

        $this->assertDatabaseHas('prescription_review_runs', [
            'patient_visit_id' => $visit->id,
            'prescription_id' => $prescription->id,
            'review_status' => 'needs_doctor_review',
            'response_language' => 'en-US',
        ]);

        $this->assertDatabaseHas('prescription_review_checks', [
            'check_key' => 'remedy_evidence_reviewed',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'patient_visit_id' => $visit->id,
            'category' => 'prescription',
            'action' => 'generated_prescription_review',
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/prescription/review')
            && $request->data()['prescription_snapshot']['remedy_name'] === 'Calcarea carbonica'
            && $request->data()['response_language'] === 'en-US');
    }

    public function test_doctor_can_confirm_prescription_review_check(): void
    {
        Http::fake([
            '*/prescription/review' => Http::response([
                'review_status' => 'needs_doctor_review',
                'safety_score' => 75,
                'review_summary' => 'Review generated.',
                'decision_guidance' => 'Confirm checks.',
                'risk_summary' => 'No blocking risk.',
                'red_flags' => [],
                'missing_information' => [],
                'doctor_review_points' => [],
                'recommended_actions' => [],
                'checks' => [
                    [
                        'check_key' => 'remedy_evidence_reviewed',
                        'category' => 'remedy',
                        'severity' => 'important',
                        'status' => 'pending',
                        'is_required' => true,
                        'is_blocking' => false,
                        'title' => 'Remedy evidence reviewed',
                        'description' => 'Confirm remedy.',
                        'ai_assessment' => 'Doctor review required.',
                        'evidence' => [],
                        'metadata' => [],
                    ],
                ],
                'safety_note' => 'Doctor-facing only.',
            ], 200),
        ]);

        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        $prescription = PatientPrescription::create([
            'patient_visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'remedy_name' => 'Calcarea carbonica',
            'remedy_code' => 'calc',
            'potency' => '200C',
            'repetition' => 'single dose',
            'status' => 'draft',
        ]);

        $this->actingAs($doctor);

        $review = $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/prescription-reviews/generate", [
            'prescription_id' => $prescription->id,
        ])->json('data');

        $checkId = $review['checks'][0]['id'];
        $reviewId = $review['id'];

        $this->patchJson("/api/patients/{$patient->id}/visits/{$visit->id}/prescription-reviews/{$reviewId}/checks/{$checkId}", [
            'status' => 'doctor_confirmed',
            'doctor_note' => 'Reviewed with totality.',
        ])
            ->assertOk()
            ->assertJsonPath('data.checks.0.status', 'doctor_confirmed')
            ->assertJsonPath('data.review_status', 'ready');

        $this->assertDatabaseHas('prescription_review_checks', [
            'id' => $checkId,
            'status' => 'doctor_confirmed',
            'doctor_note' => 'Reviewed with totality.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'patient_visit_id' => $visit->id,
            'category' => 'prescription',
            'action' => 'confirmed_prescription_review_check',
        ]);
    }
}
