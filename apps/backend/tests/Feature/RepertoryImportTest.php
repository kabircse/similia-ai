<?php

namespace Tests\Feature;

use App\Models\Remedy;
use App\Models\RepertoryRubric;
use App\Models\RepertoryRubricRemedy;
use App\Models\RepertorySource;
use App\Models\User;
use App\Services\Remedies\RemedyNormalizer;
use App\Services\Remedies\RemedyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RepertoryImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_repertory_rubrics_and_remedies_can_be_imported(): void
    {
        $base = storage_path('app/testing/repertory-import');

        File::ensureDirectoryExists($base);

        File::put($base.'/repertories.csv', "\xEF\xBB\xBF".implode("\n", [
            'id,name,abbreviation,author,edition,remedies_count,rubrics_count',
            '4,Kent Repertory,kent,James Tyler Kent,"6th Edition",2,2',
        ]));

        File::put($base.'/rubrics.csv', implode("\n", [
            'id,repertory_id,chapter_name,text_bn,text_en,medicine_count,rubric_weight,rubric_type',
            '2,4,Mind,,Mind,169,1,',
            '6,4,Mind,,"Mind, anxiety",91,2,',
        ]));

        File::put($base.'/rubric_remedies.csv', implode("\n", [
            'id,rubric_id,remedy_id,repertory_id,grade',
            '1,6,3,4,2',
        ]));

        $normalizer = app(RemedyNormalizer::class);
        $remedy = Remedy::create([
            'code' => 'abrot',
            'name' => 'Abrotanum',
            'abbreviation' => 'Abrot.',
            'normalized_name' => $normalizer->normalize('Abrotanum'),
            'normalized_abbreviation' => $normalizer->normalize('Abrot.'),
            'source' => 'legacy_sql',
            'external_id' => 3,
        ]);

        app(RemedyResolver::class)->syncDefaultAliases($remedy, 'legacy_sql');

        $this->artisan('import:legacy-repertories', [
            'path' => 'storage/app/testing/repertory-import/repertories.csv',
            '--source' => 'legacy_sql',
        ])->assertExitCode(0);

        $this->artisan('import:legacy-rubrics', [
            'path' => 'storage/app/testing/repertory-import/rubrics.csv',
            '--source' => 'legacy_sql',
        ])->assertExitCode(0);

        $this->artisan('import:legacy-rubric-remedies', [
            'path' => 'storage/app/testing/repertory-import/rubric_remedies.csv',
            '--source' => 'legacy_sql',
            '--remedy-source' => 'legacy_sql',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('repertory_sources', [
            'source' => 'legacy_sql',
            'external_id' => 4,
            'abbreviation' => 'kent',
        ]);

        $this->assertDatabaseHas('repertory_rubrics', [
            'source' => 'legacy_sql',
            'external_id' => 6,
            'rubric_path' => 'Mind > Anxiety',
            'is_selectable' => true,
        ]);

        $this->assertDatabaseHas('repertory_rubrics', [
            'source' => 'legacy_sql',
            'external_id' => 2,
            'rubric_path' => 'Mind',
            'is_selectable' => false,
        ]);

        $rubric = RepertoryRubric::where('external_id', 6)->firstOrFail();

        $this->assertDatabaseHas('repertory_rubric_remedies', [
            'repertory_rubric_id' => $rubric->id,
            'remedy_id' => $remedy->id,
            'remedy_code' => 'abrot',
            'grade' => 2,
        ]);

        $this->assertSame(1, RepertorySource::count());
        $this->assertSame(2, RepertoryRubric::count());
        $this->assertSame(1, RepertoryRubricRemedy::count());

        File::deleteDirectory($base);
    }

    public function test_repertory_search_hides_non_selectable_rubrics_by_default(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        $source = RepertorySource::create([
            'source' => 'legacy_sql',
            'external_id' => 4,
            'name' => 'Kent Repertory',
        ]);

        RepertoryRubric::create([
            'repertory_source_id' => $source->id,
            'external_id' => 2,
            'external_repertory_id' => 4,
            'source' => 'legacy_sql',
            'chapter' => 'Mind',
            'rubric_path' => 'Mind',
            'rubric_text' => 'Mind',
            'medicine_count' => 169,
            'default_weight' => 1,
            'is_selectable' => false,
        ]);

        RepertoryRubric::create([
            'repertory_source_id' => $source->id,
            'external_id' => 6,
            'external_repertory_id' => 4,
            'source' => 'legacy_sql',
            'chapter' => 'Mind',
            'rubric_path' => 'Mind > Anxiety',
            'rubric_text' => 'Mind, anxiety',
            'medicine_count' => 91,
            'default_weight' => 2,
            'is_selectable' => true,
        ]);

        $this->actingAs($doctor)
            ->getJson('/api/repertory/rubrics?search=Mind&per_page=20')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rubric_path', 'Mind > Anxiety')
            ->assertJsonPath('data.0.is_selectable', true);

        $this->actingAs($doctor)
            ->getJson('/api/repertory/rubrics?search=Mind&include_non_selectable=true&per_page=20')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
