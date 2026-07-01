<?php

namespace Tests\Feature;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Models\Patient;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\User;
use App\Services\Knowledge\SimpleTextEmbedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PotencyGuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_generate_potency_guidance(): void
    {
        Http::fake([
            '*/potency/guidance' => Http::response([
                'case_phase' => 'chronic',
                'vitality_level' => 'moderate',
                'sensitivity_level' => 'moderate',
                'pathology_depth' => 'functional',
                'guidance_summary' => 'Potency guidance for Calcarea carbonica.',
                'repetition_guidance' => 'Do not repeat mechanically.',
                'wait_and_watch_guidance' => 'Wait while improvement continues.',
                'aggravation_guidance' => 'Review timing and duration of aggravation.',
                'cautions' => [
                    'Final decision must be made by practitioner.',
                ],
                'follow_up_questions' => [
                    'Is improvement continuing?',
                ],
                'doctor_review_points' => [
                    'Review vitality and sensitivity.',
                ],
                'options' => [
                    [
                        'potency_range' => 'medium',
                        'potency_label' => '30C / 200C consideration',
                        'rank' => 1,
                        'suitability_score' => 72,
                        'rationale' => 'Moderate vitality and functional pathology.',
                        'repetition_note' => 'Wait if improving.',
                        'caution' => 'Do not repeat automatically.',
                        'source_chunks' => [],
                        'metadata' => [],
                    ],
                ],
                'safety_note' => 'Doctor-facing potency guidance only.',
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
            'chief_complaint' => 'Chronic anxiety and chilly constitution',
            'raw_case_text' => 'Chilly, low thirst, desire sweets.',
        ]);

        $prescription = PatientPrescription::create([
            'patient_visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'source_method' => 'manual',
            'remedy_name' => 'Calcarea carbonica',
            'remedy_code' => 'calc',
            'potency' => '200C',
            'repetition' => 'single dose',
            'status' => 'final',
        ]);

        $this->seedKnowledgeChunk();

        $this->actingAs($doctor);

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/potency-guidance/generate", [
            'prescription_id' => $prescription->id,
            'case_phase' => 'chronic',
            'patient_sensitivity' => 'moderate',
            'vitality_level' => 'moderate',
            'pathology_depth' => 'functional',
        ])
            ->assertCreated()
            ->assertJsonPath('data.case_phase', 'chronic')
            ->assertJsonPath('data.options.0.potency_range', 'medium');

        $this->assertDatabaseHas('potency_guidance_runs', [
            'patient_visit_id' => $visit->id,
            'prescription_id' => $prescription->id,
            'case_phase' => 'chronic',
        ]);

        $this->assertDatabaseHas('potency_guidance_options', [
            'potency_range' => 'medium',
            'potency_label' => '30C / 200C consideration',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'patient_visit_id' => $visit->id,
            'category' => 'potency',
            'action' => 'generated_potency_guidance',
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/potency/guidance'));
    }

    private function seedKnowledgeChunk(): void
    {
        $source = KnowledgeSource::create([
            'source' => 'test',
            'code' => 'potency_test',
            'title' => 'Potency Test Notes',
            'source_type' => 'potency',
            'visibility' => 'global_demo',
            'is_active' => true,
        ]);

        $chunk = KnowledgeChunk::create([
            'knowledge_source_id' => $source->id,
            'source' => 'test',
            'source_type' => 'potency',
            'book_code' => 'potency_test',
            'section_no' => 1,
            'chunk_index' => 0,
            'title' => 'Repetition',
            'content' => 'Do not repeat while improvement continues.',
            'content_hash' => hash('sha256', 'Do not repeat while improvement continues.'),
        ]);

        $embedding = app(SimpleTextEmbedding::class);

        $vector = $embedding->toPgVector(
            $embedding->embed($chunk->content)
        );

        DB::statement(
            'UPDATE knowledge_chunks SET embedding = ?::vector WHERE id = ?',
            [$vector, $chunk->id]
        );
    }
}
