<?php

namespace Tests\Feature;

use App\Models\Remedy;
use App\Services\Remedies\RemedyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RemedyImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_remedies_csv_can_be_imported(): void
    {
        $path = storage_path('app/testing/remedies.csv');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, implode("\n", [
            'id,name,abbreviation',
            '1,Abies Canadensis,Abies-c.',
            '2,Abies Nigra,Abies-n.',
            '3,Abrotanum,Abrot.',
        ]));

        $this->artisan('import:legacy-remedies', [
            'path' => 'storage/app/testing/remedies.csv',
            '--source' => 'test_csv',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('remedies', [
            'name' => 'Abrotanum',
            'abbreviation' => 'Abrot.',
            'source' => 'test_csv',
            'external_id' => 3,
        ]);

        $remedy = app(RemedyResolver::class)->findByText('Abrot.');

        $this->assertNotNull($remedy);
        $this->assertSame('Abrotanum', $remedy->name);

        File::delete($path);
    }

    public function test_remedy_aliases_are_created(): void
    {
        $path = storage_path('app/testing/remedies.csv');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, implode("\n", [
            'id,name,abbreviation',
            '3,Abrotanum,Abrot.',
        ]));

        $this->artisan('import:legacy-remedies', [
            'path' => 'storage/app/testing/remedies.csv',
            '--source' => 'test_csv',
        ])->assertExitCode(0);

        $remedy = Remedy::where('name', 'Abrotanum')->firstOrFail();

        $this->assertDatabaseHas('remedy_aliases', [
            'remedy_id' => $remedy->id,
            'alias' => 'Abrotanum',
        ]);

        $this->assertDatabaseHas('remedy_aliases', [
            'remedy_id' => $remedy->id,
            'alias' => 'Abrot.',
        ]);

        File::delete($path);
    }
}
