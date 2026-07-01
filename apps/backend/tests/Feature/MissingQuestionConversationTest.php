<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MissingQuestionConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_start_missing_question_conversation(): void
    {
        Http::fake([
            '*/case/missing-question-conversation/start' => Http::response([
                'questions' => [
                    [
                        'question_key' => 'q_1_thermal',
                        'category' => 'thermal_state',
                        'importance' => 'important',
                        'question' => 'রোগীর শীত বেশি লাগে নাকি গরম?',
                    ],
                    [
                        'question_key' => 'q_2_thirst',
                        'category' => 'thirst',
                        'importance' => 'normal',
                        'question' => 'পিপাসা কেমন?',
                    ],
                ],
                'safety_note' => 'Doctor-side only.',
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
            'chief_complaint' => 'Chilly patient',
            'raw_case_text' => 'শীত বেশি লাগে।',
        ]);

        $this->actingAs($doctor);

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/question-sessions/start", [
            'language' => 'bn-BD',
            'response_language' => 'en-US',
            'max_questions' => 5,
            'replace_active_session' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.total_questions', 2)
            ->assertJsonPath('data.messages.0.content', 'রোগীর শীত বেশি লাগে নাকি গরম?');

        $this->assertDatabaseHas('case_question_sessions', [
            'patient_visit_id' => $visit->id,
            'doctor_id' => $doctor->id,
            'status' => 'active',
            'total_questions' => 2,
        ]);

        $this->assertDatabaseHas('case_question_messages', [
            'patient_visit_id' => $visit->id,
            'role' => 'assistant',
            'message_type' => 'question',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'patient_visit_id' => $visit->id,
            'category' => 'case_taking',
            'action' => 'started_missing_question_conversation',
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/case/missing-question-conversation/start')
            && $request->data()['response_language'] === 'en-US');
    }

    public function test_doctor_can_answer_question_and_update_case(): void
    {
        Http::fake([
            '*/case/missing-question-conversation/start' => Http::response([
                'questions' => [
                    [
                        'question_key' => 'q_1_thermal',
                        'category' => 'thermal_state',
                        'importance' => 'important',
                        'question' => 'রোগীর শীত বেশি লাগে নাকি গরম?',
                    ],
                ],
                'safety_note' => 'Doctor-side only.',
            ], 200),

            '*/case/missing-question-conversation/apply-answer' => Http::response([
                'case_section_updates' => [
                    'thermal_state' => 'শীত বেশি লাগে',
                    'missing_question_answers' => [
                        'q_1_thermal' => [
                            'category' => 'thermal_state',
                            'question' => 'রোগীর শীত বেশি লাগে নাকি গরম?',
                            'answer' => 'শীত বেশি লাগে',
                        ],
                    ],
                ],
                'raw_case_note' => "Q: রোগীর শীত বেশি লাগে নাকি গরম?\nA: শীত বেশি লাগে",
                'extracted_summary' => 'Thermal state updated.',
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
            'raw_case_text' => 'Initial note.',
            'case_sections' => [],
        ]);

        $this->actingAs($doctor);

        $sessionResponse = $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/question-sessions/start", [
            'language' => 'bn-BD',
            'response_language' => 'bn-BD',
        ])->json('data');

        $questionId = $sessionResponse['messages'][0]['id'];
        $sessionId = $sessionResponse['id'];

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/question-sessions/{$sessionId}/answer", [
            'question_message_id' => $questionId,
            'answer_text' => 'শীত বেশি লাগে',
            'merge_to_case_text' => true,
            'apply_to_case_sections' => true,
            'response_language' => 'hi-IN',
        ])
            ->assertOk()
            ->assertJsonPath('data.answered_questions', 1)
            ->assertJsonPath('data.status', 'completed');

        $visit->refresh();

        $this->assertSame('শীত বেশি লাগে', $visit->case_sections['thermal_state']);
        $this->assertStringContainsString('শীত বেশি লাগে', $visit->raw_case_text);

        $this->assertDatabaseHas('case_question_messages', [
            'case_question_session_id' => $sessionId,
            'role' => 'doctor',
            'message_type' => 'answer',
            'status' => 'saved',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'patient_visit_id' => $visit->id,
            'category' => 'case_taking',
            'action' => 'answered_missing_question',
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/case/missing-question-conversation/apply-answer')
            && $request->data()['response_language'] === 'hi-IN');
    }

    public function test_doctor_cannot_access_other_doctors_question_sessions(): void
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

        $this->getJson("/api/patients/{$patient->id}/visits/{$visit->id}/question-sessions")
            ->assertForbidden();
    }
}
