<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PatientHandoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_generate_patient_handout(): void
    {
        Http::fake([
            '*/patient-handout/generate' => Http::response([
                'title' => 'Patient Treatment Instructions',
                'resolved_language' => 'en-US',
                'patient_summary' => 'These are the doctor instructions.',
                'medicine_instruction' => 'Medicine: Calcarea carbonica 200C',
                'diet_lifestyle_instruction' => 'Follow general advice.',
                'follow_up_instruction' => 'Next follow-up: next month.',
                'warning_instruction' => 'Contact doctor if serious symptoms occur.',
                'warning_signs' => [
                    'Breathing difficulty',
                ],
                'do_and_dont' => [
                    'Do not repeat on your own',
                ],
                'footer_note' => "Kabir's Homeopathic Center",
                'safety_note' => 'Doctor instructions handout only.',
                'sections' => [
                    [
                        'section_key' => 'medicine',
                        'category' => 'instruction',
                        'sort_order' => 1,
                        'title' => 'Medicine Instruction',
                        'content' => 'Medicine: Calcarea carbonica 200C',
                        'is_important' => true,
                        'metadata' => [],
                    ],
                    [
                        'section_key' => 'follow_up',
                        'category' => 'follow_up',
                        'sort_order' => 2,
                        'title' => 'Follow-up',
                        'content' => 'Next follow-up: next month.',
                        'is_important' => true,
                        'metadata' => [],
                    ],
                ],
            ], 200),
        ]);

        $doctor = User::factory()->create([
            'role' => 'doctor',
        ]);

        $patient = Patient::factory()->create([
            'doctor_id' => $doctor->id,
            'name' => 'Test Patient',
        ]);

        $visit = PatientVisit::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'chief_complaint' => 'Chilly anxiety',
            'raw_case_text' => 'Chilly, low thirst, desire sweets.',
            'doctor_notes' => 'Private doctor note must not be sent.',
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
            'reason' => 'Internal remedy reasoning must not be sent.',
            'advice' => 'Report any aggravation.',
            'follow_up_date' => now()->addMonth(),
            'status' => 'draft',
        ]);

        $this->actingAs($doctor);

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/patient-handouts/generate", [
            'prescription_id' => $prescription->id,
            'response_language' => 'en-US',
            'style' => 'simple',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Patient Treatment Instructions')
            ->assertJsonPath('data.resolved_language', 'en-US')
            ->assertJsonPath('data.sections.0.section_key', 'medicine');

        $this->assertDatabaseHas('patient_handout_runs', [
            'patient_visit_id' => $visit->id,
            'prescription_id' => $prescription->id,
            'title' => 'Patient Treatment Instructions',
            'response_language' => 'en-US',
        ]);

        $this->assertDatabaseHas('patient_handout_sections', [
            'section_key' => 'medicine',
            'title' => 'Medicine Instruction',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'patient_visit_id' => $visit->id,
            'category' => 'prescription',
            'action' => 'generated_patient_handout',
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/patient-handout/generate')
            && $request->data()['prescription_snapshot']['remedy_name'] === 'Calcarea carbonica'
            && ! array_key_exists('reason', $request->data()['prescription_snapshot'])
            && ! array_key_exists('doctor_notes', $request->data()['case_snapshot'])
            && $request->data()['response_language'] === 'en-US');
    }

    public function test_doctor_can_mark_patient_handout_as_printed(): void
    {
        Http::fake([
            '*/patient-handout/generate' => Http::response([
                'title' => 'Patient Treatment Instructions',
                'resolved_language' => 'en-US',
                'patient_summary' => 'Summary.',
                'medicine_instruction' => 'Medicine.',
                'diet_lifestyle_instruction' => 'Advice.',
                'follow_up_instruction' => 'Follow-up.',
                'warning_instruction' => 'Warning.',
                'warning_signs' => [],
                'do_and_dont' => [],
                'footer_note' => 'Clinic',
                'safety_note' => 'Safety note.',
                'sections' => [
                    [
                        'section_key' => 'medicine',
                        'category' => 'instruction',
                        'sort_order' => 1,
                        'title' => 'Medicine',
                        'content' => 'Medicine.',
                        'is_important' => true,
                        'metadata' => [],
                    ],
                ],
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

        PatientPrescription::create([
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

        $handout = $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/patient-handouts/generate")
            ->json('data');

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/patient-handouts/{$handout['id']}/printed")
            ->assertOk()
            ->assertJsonPath('data.status', 'printed');

        $this->assertDatabaseHas('patient_handout_runs', [
            'id' => $handout['id'],
            'status' => 'printed',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'patient_visit_id' => $visit->id,
            'category' => 'print',
            'action' => 'printed_patient_handout',
        ]);
    }
}
