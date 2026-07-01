<?php

namespace App\Console\Commands;

use App\Models\LegacyImport;
use App\Models\RepertorySource;
use App\Services\Import\LegacyCsvReader;
use App\Services\Repertory\RubricTextNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyRubricsCommand extends Command
{
    protected $signature = 'import:legacy-rubrics
        {path : CSV path, for example storage/app/imports/legacy/rubrics.csv}
        {--source=legacy_sql : Source name}
        {--dry-run : Preview import without saving}';

    protected $description = 'Import legacy rubrics CSV into repertory_rubrics table';

    private const BATCH_SIZE = 1000;

    public function handle(
        LegacyCsvReader $csvReader,
        RubricTextNormalizer $normalizer
    ): int {
        ini_set('memory_limit', '1024M');

        $path = $this->resolvePath((string) $this->argument('path'));
        $source = (string) $this->option('source');
        $dryRun = (bool) $this->option('dry-run');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $sourcesByExternalId = RepertorySource::query()
            ->where('source', $source)
            ->whereNotNull('external_id')
            ->get(['id', 'external_id'])
            ->keyBy('external_id');

        if ($sourcesByExternalId->isEmpty()) {
            $this->error("No repertory sources found for source {$source}. Import repertories first.");

            return self::FAILURE;
        }

        $existingExternalIds = DB::table('repertory_rubrics')
            ->where('source', $source)
            ->whereNotNull('external_id')
            ->pluck('external_id')
            ->mapWithKeys(fn ($externalId) => [(string) $externalId => true])
            ->all();

        $totalRows = $csvReader->countDataRows($path);
        $legacyImport = LegacyImport::create([
            'import_type' => 'rubrics',
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
        $batch = [];
        $now = now();

        $this->info("Importing {$totalRows} rubrics from {$path}");
        $this->info("Source: {$source}");
        $this->info($dryRun ? 'Mode: dry-run' : 'Mode: import');

        if (! $dryRun) {
            $legacyImport->markRunning($totalRows);
        }

        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        foreach ($csvReader->rows($path) as $index => $row) {
            try {
                $externalId = $this->value($row, ['id', 'ID']);
                $externalRepertoryId = $this->value($row, ['repertory_id']);

                if (! $externalId || ! $externalRepertoryId) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $repertorySource = $sourcesByExternalId->get((int) $externalRepertoryId);

                if (! $repertorySource) {
                    throw new \RuntimeException("Repertory source not found for legacy repertory_id {$externalRepertoryId}");
                }

                $chapter = $normalizer->normalizeText($this->value($row, ['chapter_name', 'chapter']) ?: 'Unknown');
                $textEn = $normalizer->normalizeText($this->value($row, ['text_en', 'rubric_text', 'text']) ?: '');
                $textBn = $this->value($row, ['text_bn']);
                $medicineCount = (int) ($this->value($row, ['medicine_count']) ?: 0);
                $defaultWeight = max(1, (int) ($this->value($row, ['rubric_weight']) ?: 1));
                $rubricType = $this->value($row, ['rubric_type']);
                $rubricPath = $normalizer->pathFromLegacy($chapter, $textEn);
                $externalKey = (string) (int) $externalId;

                $payload = [
                    'import_key' => $this->importKey($source, $externalId),
                    'repertory_source_id' => $repertorySource->id,
                    'external_id' => (int) $externalId,
                    'external_repertory_id' => (int) $externalRepertoryId,
                    'source' => $source,
                    'chapter' => $chapter,
                    'rubric_path' => $rubricPath,
                    'rubric_text' => $textEn ?: $rubricPath,
                    'medicine_count' => $medicineCount,
                    'default_weight' => min($defaultWeight, 255),
                    'is_selectable' => $normalizer->isSelectable($chapter, $textEn, $medicineCount, $rubricType),
                    'metadata' => $this->json([
                        'text_bn' => $textBn,
                        'original_text_en' => $textEn,
                        'legacy_rubric_type' => $rubricType,
                        'legacy_row' => $row,
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (isset($existingExternalIds[$externalKey])) {
                    $updated++;
                } else {
                    $created++;
                    $existingExternalIds[$externalKey] = true;
                }

                if (! $dryRun) {
                    $batch[] = $payload;

                    if (count($batch) >= self::BATCH_SIZE) {
                        $this->upsertRubrics($batch);
                        $batch = [];
                    }
                }
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = [
                    'row' => $index + 2,
                    'message' => $exception->getMessage(),
                    'data' => $row,
                ];
            }

            $processed = $created + $updated + $skipped + $failed;

            if (! $dryRun && $processed % 5000 === 0) {
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
            $this->upsertRubrics($batch);
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

        if ($failed > 0) {
            $legacyImport->markFailed('Some rubric rows could not be imported.', $errors);
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

    private function upsertRubrics(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        DB::table('repertory_rubrics')->upsert($rows, ['import_key'], [
            'repertory_source_id',
            'external_id',
            'external_repertory_id',
            'source',
            'chapter',
            'rubric_path',
            'rubric_text',
            'medicine_count',
            'default_weight',
            'is_selectable',
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
        return "legacy:rubric:{$source}:{$externalId}";
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
