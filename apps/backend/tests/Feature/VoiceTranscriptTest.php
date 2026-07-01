<?php

namespace Tests\Feature;

use App\Jobs\StructureCaseJob;
use App\Models\AiTask;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use App\Models\VoiceTranscript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VoiceTranscriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_save_voice_transcript_append_to_case_text_and_queue_structuring(): void
    {
        Queue::fake();

        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'raw_case_text' => 'Existing case note.',
            'case_source' => 'manual',
        ]);

        $this->actingAs($doctor);

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/voice-transcripts", [
            'language' => 'bn-BD',
            'transcript_text' => 'রোগীর শীত বেশি লাগে এবং পিপাসা কম।',
            'segments' => [
                [
                    'text' => 'রোগীর শীত বেশি লাগে এবং পিপাসা কম।',
                    'is_final' => true,
                    'confidence' => 0.88,
                    'captured_at' => now()->toISOString(),
                ],
            ],
            'merge_to_case_text' => true,
            'merge_mode' => 'append',
        ])
            ->assertOk()
            ->assertJsonPath('data.language', 'bn-BD')
            ->assertJsonPath('data.merged_to_case_text', true)
            ->assertJsonPath('data.merge_mode', 'append')
            ->assertJsonPath('meta.queued_ai_task_id', fn ($taskId) => is_int($taskId));

        $this->assertDatabaseHas('voice_transcripts', [
            'patient_visit_id' => $visit->id,
            'doctor_id' => $doctor->id,
            'language' => 'bn-BD',
        ]);

        $visit->refresh();

        $this->assertStringContainsString('Existing case note.', $visit->raw_case_text);
        $this->assertStringContainsString('রোগীর শীত বেশি লাগে', $visit->raw_case_text);
        $this->assertSame('mixed', $visit->case_source);

        $task = AiTask::query()
            ->where('patient_visit_id', $visit->id)
            ->where('type', 'structure_case')
            ->first();

        $this->assertNotNull($task);
        $this->assertSame('voice_transcript', $task->payload['source']);
        Queue::assertPushed(StructureCaseJob::class);

        $this->assertDatabaseHas('audit_logs', [
            'patient_visit_id' => $visit->id,
            'category' => 'voice',
            'action' => 'saved_transcript',
        ]);
    }

    public function test_doctor_can_list_voice_transcripts_for_visit(): void
    {
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

        VoiceTranscript::create([
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
            'doctor_id' => $doctor->id,
            'language' => 'en-US',
            'transcript_text' => 'Patient feels chilly and anxious.',
        ]);

        $this->actingAs($doctor);

        $this->getJson("/api/patients/{$patient->id}/visits/{$visit->id}/voice-transcripts")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.language', 'en-US');
    }

    public function test_doctor_cannot_access_other_doctors_voice_transcripts(): void
    {
        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $otherDoctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $patient = Patient::factory()->create([
            'doctor_id' => $otherDoctor->id,
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $otherDoctor->id,
        ]);

        $this->actingAs($doctor);

        $this->getJson("/api/patients/{$patient->id}/visits/{$visit->id}/voice-transcripts")
            ->assertForbidden();
    }

    public function test_save_without_merge_keeps_case_text_unchanged_and_does_not_queue_structuring(): void
    {
        Queue::fake();

        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'raw_case_text' => 'Existing case note.',
            'case_source' => 'manual',
        ]);

        $this->actingAs($doctor);

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/voice-transcripts", [
            'language' => 'en-US',
            'transcript_text' => 'Patient feels chilly.',
            'merge_to_case_text' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.merged_to_case_text', false)
            ->assertJsonPath('data.merge_mode', null)
            ->assertJsonPath('meta.queued_ai_task_id', null);

        $visit->refresh();

        $this->assertSame('Existing case note.', $visit->raw_case_text);
        $this->assertSame('manual', $visit->case_source);

        $this->assertDatabaseMissing('ai_tasks', [
            'patient_visit_id' => $visit->id,
            'type' => 'structure_case',
        ]);
        Queue::assertNotPushed(StructureCaseJob::class);
    }
}
