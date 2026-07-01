<?php

namespace App\Console\Commands;

use App\Models\LegacyImport;
use App\Models\Remedy;
use App\Services\Import\LegacyCsvReader;
use App\Services\Remedies\RemedyResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyRubricRemediesCommand extends Command
{
    protected $signature = 'import:legacy-rubric-remedies
        {path : CSV path, for example storage/app/imports/legacy/rubric_remedies.csv}
        {--source=legacy_sql : Repertory source name}
        {--remedy-source=legacy_sql : Remedy source name}
        {--dry-run : Preview import without saving}';

    protected $description = 'Import legacy rubric remedies CSV into repertory_rubric_remedies table';

    private const BATCH_SIZE = 5000;

    public function handle(
        LegacyCsvReader $csvReader,
        RemedyResolver $remedyResolver
    ): int {
        ini_set('memory_limit', '1024M');

        $path = $this->resolvePath((string) $this->argument('path'));
        $source = (string) $this->option('source');
        $remedySource = (string) $this->option('remedy-source');
        $dryRun = (bool) $this->option('dry-run');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $totalRows = $csvReader->countDataRows($path);
        $legacyImport = LegacyImport::create([
            'import_type' => 'rubric_remedies',
            'source_name' => $source,
            'file_path' => $path,
            'status' => $dryRun ? 'completed' : 'pending',
            'total_rows' => $totalRows,
            'started_at' => now(),
        ]);

        if (! $dryRun && DB::getDriverName() === 'pgsql') {
            return $this->handleWithPostgresStaging(
                $csvReader,
                $legacyImport,
                $path,
                $source,
                $remedySource,
                $totalRows
            );
        }

        $rubricsByExternalId = $this->loadRubricMap($source);
        $remediesByExternalId = $this->loadRemedyMap($remedySource);

        if ($rubricsByExternalId === []) {
            $this->error("No imported rubrics found for source {$source}. Import rubrics first.");
            $legacyImport->markFailed("No imported rubrics found for source {$source}. Import rubrics first.");

            return self::FAILURE;
        }

        if ($remediesByExternalId === []) {
            $this->error("No imported remedies found for source {$remedySource}. Import remedies first.");
            $legacyImport->markFailed("No imported remedies found for source {$remedySource}. Import remedies first.");

            return self::FAILURE;
        }

        $beforeCount = $dryRun ? 0 : DB::table('repertory_rubric_remedies')->count();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $accepted = 0;
        $errors = [];
        $batch = [];
        $now = now();

        $this->info("Importing {$totalRows} rubric remedies from {$path}");
        $this->info("Repertory source: {$source}");
        $this->info("Remedy source: {$remedySource}");
        $this->info($dryRun ? 'Mode: dry-run' : 'Mode: import');

        if (! $dryRun) {
            $legacyImport->markRunning($totalRows);
        }

        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        foreach ($csvReader->rows($path) as $index => $row) {
            try {
                $externalId = $this->value($row, ['id', 'ID']);
                $externalRubricId = $this->value($row, ['rubric_id']);
                $externalRemedyId = $this->value($row, ['remedy_id']);
                $externalRepertoryId = $this->value($row, ['repertory_id']);
                $grade = max(1, min(4, (int) ($this->value($row, ['grade']) ?: 1)));

                if (! $externalRubricId || ! $externalRemedyId) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $rubric = $rubricsByExternalId[(int) $externalRubricId] ?? null;

                if (! $rubric) {
                    throw new \RuntimeException("Rubric not found for legacy rubric_id {$externalRubricId}");
                }

                $remedy = $remediesByExternalId[(int) $externalRemedyId] ?? null;

                if (! $remedy) {
                    $resolvedRemedy = $remedyResolver->findByLegacyId($externalRemedyId, $remedySource);

                    if ($resolvedRemedy instanceof Remedy) {
                        $remedy = [
                            'id' => $resolvedRemedy->id,
                            'code' => $resolvedRemedy->code,
                            'name' => $resolvedRemedy->name,
                        ];
                        $remediesByExternalId[(int) $externalRemedyId] = $remedy;
                    }
                }

                if (! $remedy) {
                    throw new \RuntimeException("Remedy not found for legacy remedy_id {$externalRemedyId}. Import remedies first.");
                }

                $accepted++;

                if ($dryRun) {
                    $created++;
                    $bar->advance();

                    continue;
                }

                $batch[] = [
                    'import_key' => $externalId
                        ? $this->importKey($source, $externalId)
                        : $this->fallbackImportKey($source, $externalRubricId, $externalRemedyId),
                    'repertory_source_id' => $rubric['repertory_source_id'],
                    'external_id' => $externalId ? (int) $externalId : null,
                    'external_rubric_id' => (int) $externalRubricId,
                    'external_remedy_id' => (int) $externalRemedyId,
                    'repertory_rubric_id' => $rubric['id'],
                    'remedy_id' => $remedy['id'],
                    'remedy_code' => $remedy['code'],
                    'remedy_name' => $remedy['name'],
                    'grade' => $grade,
                    'source' => $source,
                    'metadata' => $this->json([
                        'legacy_repertory_id' => $externalRepertoryId,
                        'legacy_row' => $row,
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= self::BATCH_SIZE) {
                    $this->upsertRubricRemedies($batch);
                    $batch = [];
                }
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = [
                    'row' => $index + 2,
                    'message' => $exception->getMessage(),
                    'data' => $row,
                ];
            }

            $processed = $accepted + $skipped + $failed;

            if (! $dryRun && $processed % 10000 === 0) {
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

        if (! $dryRun) {
            $this->upsertRubricRemedies($batch);

            $afterCount = DB::table('repertory_rubric_remedies')->count();
            $created = max(0, $afterCount - $beforeCount);
            $updated = max(0, $accepted - $created);
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
            'processed_rows' => $accepted + $skipped + $failed,
            'created_rows' => $created,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped,
            'failed_rows' => $failed,
            'errors' => array_slice($errors, 0, 50),
        ]);

        if ($failed > 0) {
            $legacyImport->markFailed('Some rubric remedy rows could not be imported.', $errors);
        } else {
            $legacyImport->markCompleted($summary);
        }

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
    }

    private function handleWithPostgresStaging(
        LegacyCsvReader $csvReader,
        LegacyImport $legacyImport,
        string $path,
        string $source,
        string $remedySource,
        int $totalRows
    ): int {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $staged = 0;
        $errors = [];
        $batch = [];
        $missingLinks = 0;

        $this->info("Importing {$totalRows} rubric remedies from {$path}");
        $this->info("Repertory source: {$source}");
        $this->info("Remedy source: {$remedySource}");
        $this->info('Mode: import');
        $this->info('Using PostgreSQL staging import.');

        $legacyImport->markRunning($totalRows);

        if (! DB::table('repertory_rubrics')->where('source', $source)->whereNotNull('external_id')->exists()) {
            $message = "No imported rubrics found for source {$source}. Import rubrics first.";
            $legacyImport->markFailed($message);
            $this->error($message);

            return self::FAILURE;
        }

        if (! DB::table('remedies')->where('source', $remedySource)->whereNotNull('external_id')->exists()) {
            $message = "No imported remedies found for source {$remedySource}. Import remedies first.";
            $legacyImport->markFailed($message);
            $this->error($message);

            return self::FAILURE;
        }

        DB::statement('DROP TABLE IF EXISTS legacy_rubric_remedies_staging');
        DB::statement(
            'CREATE TEMP TABLE legacy_rubric_remedies_staging (
                external_id bigint null,
                external_rubric_id bigint not null,
                external_remedy_id bigint not null,
                external_repertory_id bigint null,
                grade integer not null default 1
            )'
        );

        foreach ($csvReader->rows($path) as $index => $row) {
            try {
                $externalId = $this->value($row, ['id', 'ID']);
                $externalRubricId = $this->value($row, ['rubric_id']);
                $externalRemedyId = $this->value($row, ['remedy_id']);
                $externalRepertoryId = $this->value($row, ['repertory_id']);
                $grade = max(1, min(4, (int) ($this->value($row, ['grade']) ?: 1)));

                if (! $externalRubricId || ! $externalRemedyId) {
                    $skipped++;

                    continue;
                }

                $batch[] = [
                    'external_id' => $externalId ? (int) $externalId : null,
                    'external_rubric_id' => (int) $externalRubricId,
                    'external_remedy_id' => (int) $externalRemedyId,
                    'external_repertory_id' => $externalRepertoryId ? (int) $externalRepertoryId : null,
                    'grade' => $grade,
                ];
                $staged++;

                if (count($batch) >= self::BATCH_SIZE) {
                    $this->insertStagingRows($batch);
                    $batch = [];
                }
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = [
                    'row' => $index + 2,
                    'message' => $exception->getMessage(),
                    'data' => $row,
                ];
            }

            $processed = $staged + $skipped + $failed;

            if ($processed % 50000 === 0) {
                $legacyImport->update([
                    'processed_rows' => $processed,
                    'skipped_rows' => $skipped,
                    'failed_rows' => $failed,
                    'errors' => array_slice($errors, 0, 50),
                ]);
            }
        }

        $this->insertStagingRows($batch);

        DB::statement('CREATE INDEX legacy_rr_staging_rubric_idx ON legacy_rubric_remedies_staging (external_rubric_id)');
        DB::statement('CREATE INDEX legacy_rr_staging_remedy_idx ON legacy_rubric_remedies_staging (external_remedy_id)');
        DB::statement('ANALYZE legacy_rubric_remedies_staging');

        $joinable = DB::table('legacy_rubric_remedies_staging as staging')
            ->join('repertory_rubrics as rubric', function ($join) use ($source) {
                $join->on('rubric.external_id', '=', 'staging.external_rubric_id')
                    ->where('rubric.source', '=', $source);
            })
            ->join('remedies as remedy', function ($join) use ($remedySource) {
                $join->on('remedy.external_id', '=', 'staging.external_remedy_id')
                    ->where('remedy.source', '=', $remedySource);
            })
            ->count();

        $missingLinks = max(0, $staged - $joinable);

        if ($missingLinks > 0) {
            $failed += $missingLinks;
            $errors[] = [
                'message' => "{$missingLinks} staged rows could not be linked to an imported rubric and remedy.",
            ];
        }

        $beforeCount = DB::table('repertory_rubric_remedies')
            ->where('source', $source)
            ->count();

        $this->insertJoinedStagingRows($source, $remedySource);

        $afterCount = DB::table('repertory_rubric_remedies')
            ->where('source', $source)
            ->count();

        $created = max(0, $afterCount - $beforeCount);
        $updated = max(0, $joinable - $created);

        $summary = [
            'dry_run' => false,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'staged' => $staged,
            'linked' => $joinable,
        ];

        $legacyImport->update([
            'processed_rows' => min($totalRows, $staged + $skipped + $failed - $missingLinks),
            'created_rows' => $created,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped,
            'failed_rows' => $failed,
            'errors' => array_slice($errors, 0, 50),
        ]);

        if ($failed > 0) {
            $legacyImport->markFailed('Some rubric remedy rows could not be linked.', $errors);
        } else {
            $legacyImport->markCompleted($summary);
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $created],
                ['Updated', $updated],
                ['Skipped', $skipped],
                ['Failed', $failed],
                ['Staged', $staged],
                ['Linked', $joinable],
            ]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function insertStagingRows(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        DB::table('legacy_rubric_remedies_staging')->insert($rows);
    }

    private function insertJoinedStagingRows(string $source, string $remedySource): void
    {
        DB::affectingStatement(
            <<<'SQL'
                INSERT INTO repertory_rubric_remedies (
                    import_key,
                    repertory_source_id,
                    external_id,
                    external_rubric_id,
                    external_remedy_id,
                    repertory_rubric_id,
                    remedy_id,
                    remedy_code,
                    remedy_name,
                    grade,
                    source,
                    metadata,
                    created_at,
                    updated_at
                )
                SELECT
                    CASE
                        WHEN staging.external_id IS NOT NULL
                            THEN CONCAT('legacy:rubric-remedy:', CAST(? AS text), ':', staging.external_id)
                        ELSE CONCAT(
                            'legacy:rubric-remedy:',
                            CAST(? AS text),
                            ':',
                            staging.external_rubric_id,
                            ':',
                            staging.external_remedy_id
                        )
                    END,
                    rubric.repertory_source_id,
                    staging.external_id,
                    staging.external_rubric_id,
                    staging.external_remedy_id,
                    rubric.id,
                    remedy.id,
                    remedy.code,
                    remedy.name,
                    GREATEST(1, LEAST(4, COALESCE(staging.grade, 1)))::smallint,
                    CAST(? AS text),
                    jsonb_build_object(
                        'legacy_repertory_id', staging.external_repertory_id,
                        'legacy_row', jsonb_build_object(
                            'id', staging.external_id,
                            'rubric_id', staging.external_rubric_id,
                            'remedy_id', staging.external_remedy_id,
                            'repertory_id', staging.external_repertory_id,
                            'grade', staging.grade
                        )
                    ),
                    NOW(),
                    NOW()
                FROM legacy_rubric_remedies_staging AS staging
                INNER JOIN repertory_rubrics AS rubric
                    ON rubric.source = CAST(? AS text)
                    AND rubric.external_id = staging.external_rubric_id
                INNER JOIN remedies AS remedy
                    ON remedy.source = CAST(? AS text)
                    AND remedy.external_id = staging.external_remedy_id
                ON CONFLICT (repertory_rubric_id, remedy_code)
                DO UPDATE SET
                    import_key = EXCLUDED.import_key,
                    repertory_source_id = EXCLUDED.repertory_source_id,
                    external_id = EXCLUDED.external_id,
                    external_rubric_id = EXCLUDED.external_rubric_id,
                    external_remedy_id = EXCLUDED.external_remedy_id,
                    remedy_id = EXCLUDED.remedy_id,
                    remedy_code = EXCLUDED.remedy_code,
                    remedy_name = EXCLUDED.remedy_name,
                    grade = EXCLUDED.grade,
                    source = EXCLUDED.source,
                    metadata = EXCLUDED.metadata,
                    updated_at = EXCLUDED.updated_at
                SQL,
            [$source, $source, $source, $source, $remedySource]
        );
    }

    private function loadRubricMap(string $source): array
    {
        $rubrics = [];

        foreach (DB::table('repertory_rubrics')
            ->select(['id', 'external_id', 'repertory_source_id'])
            ->where('source', $source)
            ->whereNotNull('external_id')
            ->orderBy('id')
            ->cursor() as $rubric) {
            $rubrics[(int) $rubric->external_id] = [
                'id' => (int) $rubric->id,
                'repertory_source_id' => $rubric->repertory_source_id ? (int) $rubric->repertory_source_id : null,
            ];
        }

        return $rubrics;
    }

    private function loadRemedyMap(string $source): array
    {
        $remedies = [];

        foreach (DB::table('remedies')
            ->select(['id', 'external_id', 'code', 'name'])
            ->where('source', $source)
            ->whereNotNull('external_id')
            ->orderBy('id')
            ->cursor() as $remedy) {
            $remedies[(int) $remedy->external_id] = [
                'id' => (int) $remedy->id,
                'code' => (string) $remedy->code,
                'name' => (string) $remedy->name,
            ];
        }

        return $remedies;
    }

    private function upsertRubricRemedies(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        DB::table('repertory_rubric_remedies')->upsert($rows, ['repertory_rubric_id', 'remedy_code'], [
            'import_key',
            'repertory_source_id',
            'external_id',
            'external_rubric_id',
            'external_remedy_id',
            'remedy_id',
            'remedy_code',
            'remedy_name',
            'grade',
            'source',
            'metadata',
            'updated_at',
        ]);
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

    private function importKey(string $source, int|string $externalId): string
    {
        return "legacy:rubric-remedy:{$source}:{$externalId}";
    }

    private function fallbackImportKey(string $source, int|string $externalRubricId, int|string $externalRemedyId): string
    {
        return "legacy:rubric-remedy:{$source}:{$externalRubricId}:{$externalRemedyId}";
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }
}
