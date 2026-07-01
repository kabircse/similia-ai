<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientPortalInvitation;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class PatientPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_create_patient_portal_invitation(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'name' => 'Test Patient',
        ]);
        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        $this->actingAs($doctor);

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/portal-invitations", [
            'expires_in_days' => 7,
            'response_language' => 'bn-BD',
            'message_to_patient' => 'Please submit your follow-up update.',
        ])
            ->assertOk()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.response_language', 'bn-BD')
            ->assertJsonStructure(['data' => ['portal_url']]);

        $this->assertDatabaseHas('patient_portal_invitations', [
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
            'doctor_id' => $doctor->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'patient_portal',
            'action' => 'created_patient_portal_invitation',
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
        ]);
    }

    public function test_patient_can_open_and_submit_follow_up_form(): void
    {
        [$patient, $visit, $invitation, $secret] = $this->makeInvitation();

        $this->getJson("/api/patient-portal/follow-up/{$invitation->public_id}/{$secret}")
            ->assertOk()
            ->assertJsonPath('data.patient.name', $patient->name)
            ->assertJsonPath('data.visit.chief_complaint', $visit->chief_complaint);

        $this->postJson("/api/patient-portal/follow-up/{$invitation->public_id}/{$secret}", [
            'overall_change' => 'improved',
            'medicine_taken' => true,
            'main_changes' => 'Sleep improved.',
            'current_symptoms' => 'Mild anxiety remains.',
            'new_symptoms' => '',
            'aggravation_notes' => '',
            'red_flag_notes' => 'No warning concern.',
            'consent_to_submit' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.overall_change', 'improved')
            ->assertJsonPath('data.detected_red_flags.0', 'Patient reported warning concern');

        $this->assertDatabaseHas('patient_follow_up_submissions', [
            'patient_id' => $patient->id,
            'source_patient_visit_id' => $visit->id,
            'overall_change' => 'improved',
        ]);

        $this->assertDatabaseHas('patient_portal_invitations', [
            'id' => $invitation->id,
            'status' => 'submitted',
            'submission_count' => 1,
        ]);
    }

    public function test_expired_invitation_cannot_be_used(): void
    {
        [$patient, , $invitation, $secret] = $this->makeInvitation([
            'public_id' => '22222222-2222-2222-2222-222222222222',
            'expires_at' => now()->subDay(),
        ]);

        $this->getJson("/api/patient-portal/follow-up/{$invitation->public_id}/{$secret}")
            ->assertStatus(410);

        $this->assertDatabaseHas('patient_portal_invitations', [
            'patient_id' => $patient->id,
            'status' => 'expired',
        ]);
    }

    public function test_doctor_can_review_and_convert_submission_to_follow_up_visit(): void
    {
        [$patient, $visit, $invitation, $secret, $doctor] = $this->makeInvitation([
            'public_id' => '33333333-3333-3333-3333-333333333333',
        ]);

        $submission = $this->postJson("/api/patient-portal/follow-up/{$invitation->public_id}/{$secret}", [
            'overall_change' => 'mixed',
            'medicine_taken' => true,
            'main_changes' => 'Sleep better but headache appeared.',
            'current_symptoms' => 'Headache.',
            'new_symptoms' => 'Headache.',
            'aggravation_notes' => '',
            'red_flag_notes' => '',
            'consent_to_submit' => true,
        ])->json('data');

        $this->actingAs($doctor);

        $this->patchJson(
            "/api/patients/{$patient->id}/visits/{$visit->id}/portal-submissions/{$submission['id']}/review",
            [
                'status' => 'reviewed',
                'doctor_note' => 'Reviewed for follow-up visit.',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed');

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/portal-submissions/{$submission['id']}/convert-to-visit")
            ->assertOk()
            ->assertJsonPath('data.status', 'converted_to_visit');

        $this->assertDatabaseHas('patient_visits', [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'visit_type' => 'follow_up',
            'case_source' => 'patient_portal',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'patient_portal',
            'action' => 'converted_patient_follow_up_submission',
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
        ]);
    }

    public function test_doctor_cannot_access_other_doctors_portal_submissions(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $otherDoctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create(['doctor_id' => $otherDoctor->id]);
        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $otherDoctor->id,
        ]);

        $this->actingAs($doctor);

        $this->getJson("/api/patients/{$patient->id}/visits/{$visit->id}/portal-submissions")
            ->assertForbidden();
    }

    private function makeInvitation(array $overrides = []): array
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'name' => 'Test Patient',
        ]);
        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'chief_complaint' => 'Anxiety follow-up',
        ]);

        $secret = $overrides['secret'] ?? 'secret-token-for-test';
        unset($overrides['secret']);

        $invitation = PatientPortalInvitation::create(array_merge([
            'public_id' => '11111111-1111-1111-1111-111111111111',
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
            'doctor_id' => $doctor->id,
            'purpose' => 'follow_up_form',
            'status' => 'active',
            'response_language' => 'en-US',
            'secret_hash' => hash('sha256', $secret),
            'secret_encrypted' => Crypt::encryptString($secret),
            'token_prefix' => substr($secret, 0, 10),
            'max_submissions' => 1,
            'submission_count' => 0,
            'opened_count' => 0,
            'expires_at' => now()->addDays(7),
        ], $overrides));

        return [$patient, $visit, $invitation, $secret, $doctor];
    }
}
