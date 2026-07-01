<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Remedy;
use App\Models\RemedySuggestionItem;
use App\Models\RemedySuggestionRun;
use App\Models\RepertorizationRun;
use App\Models\User;
use App\Services\Remedies\RemedyNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RemedySuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_needs_repertorization_before_generating_suggestion(): void
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
            'chief_complaint' => 'Chilly patient with anxiety.',
            'raw_case_text' => 'Fear of disease, chilly, low thirst.',
        ]);

        $this->actingAs($doctor)
            ->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/remedy-suggestions/generate", [
                'method' => 'weighted',
                'limit' => 3,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Run repertorization first before generating remedy suggestions.');
    }

    public function test_doctor_can_generate_and_store_remedy_suggestions(): void
    {
        Http::fake([
            '*/remedy/suggest' => Http::response([
                'safety_note' => 'Doctor final decision required.',
                'suggestions' => [
                    [
                        'remedy_code' => 'calc',
                        'remedy_name' => 'Calcarea Carbonica',
                        'rank' => 1,
                        'confidence_score' => 78,
                        'repertory_score' => 40,
                        'materia_medica_score' => 24,
                        'knowledge_score' => 10,
                        'summary' => 'Calcarea is a candidate for review.',
                        'matching_points' => ['Chilly patient'],
                        'differentiating_points' => ['Confirm sweat and fears'],
                        'missing_questions' => ['How is thirst?'],
                        'evidence_matrix' => [
                            [
                                'rubric_path' => 'Mind > Anxiety',
                                'importance' => 'important',
                                'weight' => 2,
                                'is_essential' => false,
                                'covered' => true,
                            ],
                        ],
                        'repertory_evidence' => ['total_score' => 40],
                        'materia_medica_evidence' => [],
                        'potency_considerations' => [],
                        'relationship_notes' => [],
                        'medical_safety_notes' => [],
                        'source_chunks' => [],
                        'metadata' => ['engine' => 'test'],
                    ],
                ],
                'engine' => 'test_engine',
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
            'chief_complaint' => 'Chilly patient with anxiety.',
            'raw_case_text' => 'Fear of disease, chilly, low thirst.',
            'case_sections' => [
                'thermal_state' => 'Chilly',
                'mentals' => 'Anxiety',
            ],
        ]);

        $normalizer = app(RemedyNormalizer::class);
        Remedy::create([
            'code' => 'calc',
            'name' => 'Calcarea Carbonica',
            'abbreviation' => 'Calc.',
            'normalized_name' => $normalizer->normalize('Calcarea Carbonica'),
            'normalized_abbreviation' => $normalizer->normalize('Calc.'),
            'source' => 'legacy_sql',
            'external_id' => 10,
        ]);

        $run = RepertorizationRun::create([
            'patient_visit_id' => $visit->id,
            'doctor_id' => $doctor->id,
            'method' => 'weighted',
            'total_rubrics' => 1,
            'essential_rubrics_count' => 0,
            'settings' => [],
            'selected_rubrics_snapshot' => [],
        ]);

        $run->results()->create([
            'remedy_code' => 'calc',
            'remedy_name' => 'Calcarea Carbonica',
            'total_score' => 40,
            'rubric_coverage' => 1,
            'essential_coverage' => 0,
            'rank' => 1,
            'supporting_rubrics' => [
                [
                    'rubric_path' => 'Mind > Anxiety',
                    'remedy_grade' => 2,
                ],
            ],
            'missing_important_rubrics' => [],
        ]);

        $this->actingAs($doctor)
            ->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/remedy-suggestions/generate", [
                'method' => 'weighted',
                'limit' => 3,
                'response_language' => 'en-US',
            ])
            ->assertCreated()
            ->assertJsonPath('data.method', 'weighted')
            ->assertJsonPath('data.safety_note', 'Doctor final decision required.')
            ->assertJsonPath('data.items.0.remedy_code', 'calc');

        $this->assertSame(1, RemedySuggestionRun::count());
        $this->assertSame(1, RemedySuggestionItem::count());

        $this->assertDatabaseHas('remedy_suggestion_items', [
            'remedy_code' => 'calc',
            'remedy_name' => 'Calcarea Carbonica',
            'rank' => 1,
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/remedy/suggest')
            && $request->data()['response_language'] === 'en-US'
            && $request->data()['settings']['response_language'] === 'en-US');
    }
}
