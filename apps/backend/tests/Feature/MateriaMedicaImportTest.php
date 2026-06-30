<?php

namespace Tests\Feature;

use App\Models\MateriaMedicaChunk;
use App\Models\MateriaMedicaSource;
use App\Models\Remedy;
use App\Services\Remedies\RemedyNormalizer;
use App\Services\Remedies\RemedyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MateriaMedicaImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_materia_medica_sources_and_contents_can_be_imported_with_embeddings(): void
    {
        $base = storage_path('app/testing/materia-medica-import');
        File::deleteDirectory($base);
        File::ensureDirectoryExists($base);

        File::put($base.'/materia_media.csv', implode("\n", [
            'id,name,author,abbreviation,edition,remedies',
            '1,Allen Keynotes,Henry C. Allen,allen,Keynotes,183',
        ]));

        File::put($base.'/materia_media_contents.csv', implode("\n", [
            'id,mm_id,remedy_id,content',
            '1,1,3,"# Abrotanum
### Abrot.

# Keynotes
- Alternate constipation and diarrhoea.
- Marasmus of children with marked emaciation.

# General
Weakness and wasting after suppressed discharges."',
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

        $this->artisan('import:legacy-materia-medica-sources', [
            'path' => 'storage/app/testing/materia-medica-import/materia_media.csv',
            '--source' => 'legacy_sql',
        ])->assertExitCode(0);

        $this->artisan('import:legacy-materia-medica-contents', [
            'path' => 'storage/app/testing/materia-medica-import/materia_media_contents.csv',
            '--source' => 'legacy_sql',
            '--remedy-source' => 'legacy_sql',
        ])->assertExitCode(0);

        $this->assertSame(1, MateriaMedicaSource::count());

        $this->assertDatabaseHas('materia_medica_sources', [
            'source' => 'legacy_sql',
            'external_id' => 1,
            'name' => 'Allen Keynotes',
        ]);

        $this->assertGreaterThanOrEqual(1, MateriaMedicaChunk::count());

        $chunk = MateriaMedicaChunk::query()
            ->where('section', 'Keynotes')
            ->firstOrFail();

        $this->assertSame($remedy->id, $chunk->remedy_id);
        $this->assertSame('abrot', $chunk->remedy_code);
        $this->assertSame('Abrotanum', $chunk->remedy_name);
        $this->assertSame(1, $chunk->external_id);
        $this->assertSame(1, $chunk->external_mm_id);
        $this->assertSame(3, $chunk->external_remedy_id);
        $this->assertNotNull($chunk->content_hash);

        $embeddingCount = MateriaMedicaChunk::query()
            ->whereNotNull('embedding')
            ->count();

        $this->assertGreaterThan(0, $embeddingCount);

        File::deleteDirectory($base);
    }
}
