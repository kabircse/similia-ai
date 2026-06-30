<?php

namespace App\Console\Commands;

use App\Models\LegacyImport;
use App\Models\RepertorySource;
use App\Services\Import\LegacyCsvReader;
use Illuminate\Console\Command;

class ImportLegacyRepertoriesCommand extends Command
{
    protected $signature = 'import:legacy-repertories
        {path : CSV path, for example storage/app/imports/legacy/repertories.csv}
        {--source=legacy_sql : Source name}
        {--dry-run : Preview import without saving}';

    protected $description = 'Import legacy repertories CSV into repertory_sources table';

    public function handle(LegacyCsvReader $csvReader): int
    {
        $path = $this->resolvePath((string) $this->argument('path'));
        $source = (string) $this->option('source');
        $dryRun = (bool) $this->option('dry-run');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $totalRows = $csvReader->countDataRows($path);
        $legacyImport = LegacyImport::create([
            'import_type' => 'repertories',
            'source_name' => $source,
            'file_path' => $path,
            'status' => $dryRun ? 'completed' : 'pending',
            'total_rows' => $totalRows,
            'started_at' => now(),
        ]);

        $created = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        $this->info("Importing {$totalRows} repertories from {$path}");
        $this->info("Source: {$source}");
        $this->info($dryRun ? 'Mode: dry-run' : 'Mode: import');

        if (! $dryRun) {
            $legacyImport->markRunning($totalRows);
        }

        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        foreach ($csvReader->rows($path) as $index => $row) {
            try {
                $externalId = $this->value($row, ['id', 'ID', 'repertory_id']);
                $name = $this->value($row, ['name', 'Name']) ?: 'Unknown Repertory';
                $lookup = $externalId
                    ? ['source' => $source, 'external_id' => (int) $externalId]
                    : ['source' => $source, 'name' => $name];

                $payload = [
                    'source' => $source,
                    'external_id' => $externalId ? (int) $externalId : null,
                    'name' => $name,
                    'abbreviation' => $this->value($row, ['abbreviation', 'abbr']),
                    'author' => $this->value($row, ['author']),
                    'edition' => $this->value($row, ['edition']),
                    'remedies_count' => (int) ($this->value($row, ['remedies_count']) ?: 0),
                    'rubrics_count' => (int) ($this->value($row, ['rubrics_count']) ?: 0),
                    'metadata' => [
                        'legacy_row' => $row,
                    ],
                ];

                $exists = RepertorySource::query()->where($lookup)->exists();

                if (! $dryRun) {
                    RepertorySource::updateOrCreate($lookup, $payload);
                }

                $exists ? $updated++ : $created++;
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = [
                    'row' => $index + 2,
                    'message' => $exception->getMessage(),
                    'data' => $row,
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $summary = [
            'dry_run' => $dryRun,
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
        ];

        $legacyImport->update([
            'processed_rows' => $created + $updated + $failed,
            'created_rows' => $created,
            'updated_rows' => $updated,
            'failed_rows' => $failed,
            'errors' => array_slice($errors, 0, 50),
        ]);

        if ($failed > 0) {
            $legacyImport->markFailed('Some repertory rows could not be imported.', $errors);
        } else {
            $legacyImport->markCompleted($summary);
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $created],
                ['Updated', $updated],
                ['Failed', $failed],
            ]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }
}
