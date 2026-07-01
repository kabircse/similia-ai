<?php

namespace Tests\Feature;

use App\Models\DoctorReviewQueueItem;
use App\Models\Patient;
use App\Models\PatientPortalInvitation;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class DoctorReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_submission_creates_review_queue_item_and_notification(): void
    {
        [$patient, $visit, $invitation, $secret, $doctor] = $this->makeInvitation();

        $this->postJson("/api/patient-portal/follow-up/{$invitation->public_id}/{$secret}", [
            'overall_change' => 'worse',
            'medicine_taken' => true,
            'main_changes' => 'Patient feels worse.',
            'current_symptoms' => 'Breathing difficulty and weakness.',
            'new_symptoms' => 'Chest pain.',
            'red_flag_notes' => 'Breathing difficulty.',
            'consent_to_submit' => true,
        ])->assertOk();

        $this->assertDatabaseHas('doctor_review_queue_items', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
            'category' => 'portal_submission',
            'priority' => 'urgent',
            'status' => 'open',
            'action_url' => "/patients/{$patient->id}/visits/{$visit->id}",
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $doctor->id,
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
            'category' => 'patient_portal',
            'type' => 'warning',
            'title' => 'Urgent portal submission',
        ]);
    }

    public function test_doctor_can_view_queue_summary(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);

        DoctorReviewQueueItem::create([
            'doctor_id' => $doctor->id,
            'category' => 'portal_submission',
            'priority' => 'urgent',
            'status' => 'open',
            'title' => 'Urgent portal submission',
        ]);

        $this->actingAs($doctor);

        $this->getJson('/api/doctor-review-queue/summary')
            ->assertOk()
            ->assertJsonPath('data.open_count', 1)
            ->assertJsonPath('data.urgent_count', 1)
            ->assertJsonPath('data.portal_submission_count', 1);
    }

    public function test_doctor_can_complete_queue_item(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $item = DoctorReviewQueueItem::create([
            'doctor_id' => $doctor->id,
            'category' => 'portal_submission',
            'priority' => 'normal',
            'status' => 'open',
            'title' => 'Portal submission',
        ]);

        $this->actingAs($doctor);

        $this->patchJson("/api/doctor-review-queue/{$item->id}", [
            'status' => 'completed',
            'doctor_note' => 'Reviewed and handled.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.doctor_note', 'Reviewed and handled.');

        $this->assertDatabaseHas('doctor_review_queue_items', [
            'id' => $item->id,
            'status' => 'completed',
            'doctor_note' => 'Reviewed and handled.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'category' => 'review_queue',
            'action' => 'updated_doctor_review_queue_item',
            'entity_id' => $item->id,
        ]);
    }

    public function test_doctor_cannot_view_other_doctors_queue_items(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $otherDoctor = User::factory()->create(['role' => 'doctor']);

        DoctorReviewQueueItem::create([
            'doctor_id' => $otherDoctor->id,
            'category' => 'portal_submission',
            'priority' => 'urgent',
            'status' => 'open',
            'title' => 'Hidden item',
        ]);

        $this->actingAs($doctor);

        $this->getJson('/api/doctor-review-queue')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function makeInvitation(): array
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'name' => 'Queue Patient',
        ]);
        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);
        $secret = 'queue-secret';

        $invitation = PatientPortalInvitation::create([
            'public_id' => '44444444-4444-4444-4444-444444444444',
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
        ]);

        return [$patient, $visit, $invitation, $secret, $doctor];
    }
}
