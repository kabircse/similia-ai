<?php

namespace Tests\Feature;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Remedy;
use App\Services\Knowledge\SimpleTextEmbedding;
use App\Services\Remedies\RemedyNormalizer;
use App\Services\Remedies\RemedyResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RemedyRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_generate_remedy_relationship_guidance(): void
    {
        Http::fake([
            '*/remedy/relationship' => Http::response([
                'relationship_summary' => 'A source-backed relationship review was generated for Calcarea carbonica and Sulphur.',
                'sequence_guidance' => 'Review patient response before sequencing remedies.',
                'antidote_guidance' => 'Antidote only if clinically justified.',
                'inimical_warning' => 'Use caution with inimical relationships.',
                'complementary_note' => 'Complementary relationship does not automatically justify remedy change.',
                'cautions' => [
                    'Doctor must make final decision.',
                ],
                'doctor_review_points' => [
                    'Review current totality.',
                ],
                'suggested_questions' => [
                    'Is improvement still continuing?',
                ],
                'findings' => [
                    [
                        'related_remedy_code' => 'sulph',
                        'related_remedy_name' => 'Sulphur',
                        'relationship_type' => 'complementary',
                        'direction' => 'unclear',
                        'rank' => 1,
                        'confidence_score' => 75,
                        'summary' => 'Relationship finding summary.',
                        'clinical_note' => 'Review sequence clinically.',
                        'caution' => null,
                        'evidence' => [
                            'Relationship source evidence.',
                        ],
                        'source_chunks' => [],
                        'metadata' => [],
                    ],
                ],
                'safety_note' => 'Doctor-facing relationship guidance only.',
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
            'chief_complaint' => 'Chronic anxiety and chilly patient',
            'raw_case_text' => 'Chilly, low thirst, desire sweets.',
        ]);

        $this->seedRemedy('calc', 'Calcarea carbonica', 'Calc.');
        $this->seedRemedy('sulph', 'Sulphur', 'Sulph.');
        $this->seedRelationshipChunk();

        $this->actingAs($doctor);

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/remedy-relationships/generate", [
            'primary_remedy_name' => 'Calcarea carbonica',
            'comparison_remedy_name' => 'Sulphur',
            'purpose' => 'change_remedy',
            'response_language' => 'en-US',
        ])
            ->assertCreated()
            ->assertJsonPath('data.primary_remedy_name', 'Calcarea carbonica')
            ->assertJsonPath('data.comparison_remedy_name', 'Sulphur')
            ->assertJsonPath('data.findings.0.relationship_type', 'complementary');

        $this->assertDatabaseHas('remedy_relationship_runs', [
            'patient_visit_id' => $visit->id,
            'primary_remedy_name' => 'Calcarea carbonica',
            'comparison_remedy_name' => 'Sulphur',
            'purpose' => 'change_remedy',
            'response_language' => 'en-US',
        ]);

        $this->assertDatabaseHas('remedy_relationship_findings', [
            'related_remedy_name' => 'Sulphur',
            'relationship_type' => 'complementary',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'patient_visit_id' => $visit->id,
            'category' => 'relationship',
            'action' => 'generated_remedy_relationship',
        ]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/remedy/relationship')
            && $request->data()['primary_remedy']['remedy_name'] === 'Calcarea carbonica'
            && $request->data()['comparison_remedy']['remedy_name'] === 'Sulphur'
            && $request->data()['response_language'] === 'en-US'
            && count($request->data()['knowledge_chunks']) === 1);
    }

    public function test_relationship_guidance_requires_primary_remedy_or_prescription(): void
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

        $this->actingAs($doctor);

        $this->postJson("/api/patients/{$patient->id}/visits/{$visit->id}/remedy-relationships/generate", [
            'purpose' => 'general',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Primary remedy is required for relationship guidance.');
    }

    private function seedRemedy(string $code, string $name, string $abbreviation): Remedy
    {
        $normalizer = app(RemedyNormalizer::class);

        $remedy = Remedy::create([
            'code' => $code,
            'name' => $name,
            'abbreviation' => $abbreviation,
            'normalized_name' => $normalizer->normalize($name),
            'normalized_abbreviation' => $normalizer->normalize($abbreviation),
            'source' => 'test',
            'is_active' => true,
        ]);

        app(RemedyResolver::class)->syncDefaultAliases($remedy, 'test');

        return $remedy;
    }

    private function seedRelationshipChunk(): void
    {
        $source = KnowledgeSource::create([
            'source' => 'test',
            'code' => 'relationship_test',
            'title' => 'Relationship of Remedies Test',
            'author' => 'Test Author',
            'source_type' => 'relationship',
            'visibility' => 'global_demo',
            'is_active' => true,
        ]);

        $chunk = KnowledgeChunk::create([
            'knowledge_source_id' => $source->id,
            'source' => 'test',
            'source_type' => 'relationship',
            'book_code' => 'relationship_test',
            'section_no' => 1,
            'chunk_index' => 0,
            'title' => 'Calcarea and Sulphur',
            'content' => 'Calcarea carbonica and Sulphur may be considered in complementary remedy relationship and sequencing context.',
            'content_hash' => hash('sha256', 'relationship test'),
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
