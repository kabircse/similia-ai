<?php

namespace App\Console\Commands;

use App\Models\LegacyImport;
use App\Models\Remedy;
use App\Services\Import\LegacyCsvReader;
use App\Services\Remedies\RemedyNormalizer;
use App\Services\Remedies\RemedyResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyRemediesCommand extends Command
{
    protected $signature = 'import:legacy-remedies
        {path : CSV path, for example storage/app/imports/legacy/remedies.csv}
        {--source=legacy_csv : Source name for imported rows}
        {--dry-run : Preview import without saving}';

    protected $description = 'Import legacy remedies CSV into remedy master table';

    public function handle(
        LegacyCsvReader $csvReader,
        RemedyNormalizer $normalizer,
        RemedyResolver $remedyResolver
    ): int {
        $path = $this->resolvePath((string) $this->argument('path'));
        $source = (string) $this->option('source');
        $dryRun = (bool) $this->option('dry-run');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $totalRows = $csvReader->countDataRows($path);

        $legacyImport = LegacyImport::create([
            'import_type' => 'remedies',
            'source_name' => $source,
            'file_path' => $path,
            'status' => $dryRun ? 'completed' : 'pending',
            'total_rows' => $totalRows,
            'started_at' => now(),
        ]);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        $this->info("Importing {$totalRows} remedies from {$path}");
        $this->info("Source: {$source}");
        $this->info($dryRun ? 'Mode: dry-run' : 'Mode: import');

        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        try {
            if (! $dryRun) {
                $legacyImport->markRunning($totalRows);
            }

            foreach ($csvReader->rows($path) as $index => $row) {
                try {
                    $name = $this->value($row, ['name', 'Name', 'remedy_name']);
                    $abbreviation = $this->value($row, ['abbreviation', 'Abbreviation', 'abbr', 'code']);

                    if (! $name) {
                        $skipped++;
                        $bar->advance();

                        continue;
                    }

                    $code = $normalizer->codeFromAbbreviationOrName($abbreviation, $name);
                    $externalId = $this->value($row, ['id', 'ID', 'remedy_id']);
                    $exists = $externalId
                        ? Remedy::query()
                            ->where('source', $source)
                            ->where('external_id', (int) $externalId)
                            ->exists()
                        : Remedy::query()->where('code', $code)->exists();

                    $exists = $exists || Remedy::query()->where('code', $code)->exists();

                    if ($dryRun) {
                        $exists ? $updated++ : $created++;
                        $bar->advance();

                        continue;
                    }

                    DB::transaction(function () use ($row, $source, $remedyResolver, $exists, &$created, &$updated): void {
                        $remedyResolver->createOrUpdateFromLegacyRow($row, $source);
                        $exists ? $updated++ : $created++;
                    });
                } catch (\Throwable $exception) {
                    $failed++;

                    $errors[] = [
                        'row' => $index + 2,
                        'message' => $exception->getMessage(),
                        'data' => $row,
                    ];
                }

                $processed = $created + $updated + $skipped + $failed;

                if (! $dryRun && $processed % 100 === 0) {
                    $legacyImport->update([
                        'processed_rows' => $processed,
                        'created_rows' => $created,
                        'updated_rows' => $updated,
                        'skipped_rows' => $skipped,
                        'failed_rows' => $failed,
                        'errors' => array_slice($errors, 0, 50),
                    ]);
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            $summary = [
                'dry_run' => $dryRun,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => $failed,
            ];

            $legacyImport->update([
                'processed_rows' => $created + $updated + $skipped + $failed,
                'created_rows' => $created,
                'updated_rows' => $updated,
                'skipped_rows' => $skipped,
                'failed_rows' => $failed,
                'errors' => array_slice($errors, 0, 50),
            ]);
            $legacyImport->markCompleted($summary);

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Created', $created],
                    ['Updated', $updated],
                    ['Skipped', $skipped],
                    ['Failed', $failed],
                ]
            );

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $exception) {
            $legacyImport->markFailed($exception->getMessage(), $errors);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
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
