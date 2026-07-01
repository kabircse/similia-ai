<?php

namespace App\Console\Commands;

use App\Models\LegacyImport;
use App\Models\MateriaMedicaChunk;
use App\Models\MateriaMedicaSource;
use App\Services\Import\LegacyCsvReader;
use App\Services\Knowledge\SimpleTextEmbedding;
use App\Services\MateriaMedica\MateriaMedicaChunker;
use App\Services\Remedies\RemedyResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyMateriaMedicaContentsCommand extends Command
{
    protected $signature = 'import:legacy-materia-medica-contents
        {path : CSV path, for example storage/app/imports/legacy/materia_media_contents.csv}
        {--source=legacy_sql : Materia medica source name}
        {--remedy-source=legacy_sql : Remedy source name}
        {--dry-run : Preview import without saving}
        {--limit=0 : Limit source rows for testing}
        {--reimport : Delete previous chunks for each legacy content row before importing}';

    protected $description = 'Import legacy materia medica contents into embedded materia_medica_chunks';

    public function handle(
        LegacyCsvReader $csvReader,
        RemedyResolver $remedyResolver,
        MateriaMedicaChunker $chunker,
        SimpleTextEmbedding $embedder
    ): int {
        ini_set('memory_limit', '1024M');
        DB::connection()->disableQueryLog();

        $path = $this->resolvePath((string) $this->argument('path'));
        $source = (string) $this->option('source');
        $remedySource = (string) $this->option('remedy-source');
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $reimport = (bool) $this->option('reimport');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $totalRows = $csvReader->countDataRows($path);

        if ($limit > 0) {
            $totalRows = min($totalRows, $limit);
        }

        $legacyImport = LegacyImport::create([
            'import_type' => 'materia_medica_contents',
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
        $processedRows = 0;
        $errors = [];

        $this->info("Importing {$totalRows} materia medica content rows from {$path}");
        $this->info("Materia medica source: {$source}");
        $this->info("Remedy source: {$remedySource}");
        $this->info($dryRun ? 'Mode: dry-run' : 'Mode: import');

        if (! $dryRun) {
            $legacyImport->markRunning($totalRows);
        }

        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        foreach ($csvReader->rows($path) as $index => $row) {
            if ($limit > 0 && $processedRows >= $limit) {
                break;
            }

            $processedRows++;

            try {
                $externalId = $this->value($row, ['id', 'ID', 'content_id']);
                $externalMmId = $this->value($row, ['mm_id', 'materia_medica_id', 'materia_media_id']);
                $externalRemedyId = $this->value($row, ['remedy_id']);
                $content = $this->value($row, ['content', 'body', 'text']);

                if (! $externalId || ! $externalMmId || ! $externalRemedyId || ! $content) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $sourceModel = MateriaMedicaSource::query()
                    ->where('source', $source)
                    ->where('external_id', (int) $externalMmId)
                    ->first();

                if (! $sourceModel) {
                    throw new \RuntimeException("Materia medica source not found for mm_id {$externalMmId}. Import materia_media.csv first.");
                }

                $remedy = $remedyResolver->findByLegacyId($externalRemedyId, $remedySource);

                if (! $remedy) {
                    throw new \RuntimeException("Remedy not found for legacy remedy_id {$externalRemedyId}. Import remedies first.");
                }

                $chunks = $chunker->chunks($content);

                if ($chunks === []) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                if ($dryRun) {
                    $created += count($chunks);
                    $bar->advance();

                    continue;
                }

                DB::transaction(function () use (
                    $chunks,
                    $embedder,
                    $source,
                    $sourceModel,
                    $remedy,
                    $externalId,
                    $externalMmId,
                    $externalRemedyId,
                    $row,
                    $reimport,
                    &$created,
                    &$updated
                ): void {
                    if ($reimport) {
                        MateriaMedicaChunk::query()
                            ->where('source', $source)
                            ->where('external_id', (int) $externalId)
                            ->delete();
                    }

                    foreach ($chunks as $chunkIndex => $chunk) {
                        $importKey = $this->importKey($source, $externalId, $chunkIndex);
                        $contentHash = hash('sha256', $source.'|'.$externalId.'|'.$chunkIndex.'|'.$chunk['content']);
                        $exists = MateriaMedicaChunk::query()
                            ->where('import_key', $importKey)
                            ->exists();

                        $chunkModel = MateriaMedicaChunk::updateOrCreate(
                            ['import_key' => $importKey],
                            [
                                'materia_medica_source_id' => $sourceModel->id,
                                'external_id' => (int) $externalId,
                                'external_mm_id' => (int) $externalMmId,
                                'external_remedy_id' => (int) $externalRemedyId,
                                'source' => $source,
                                'source_title' => $sourceModel->name,
                                'remedy_id' => $remedy->id,
                                'remedy_code' => $remedy->code,
                                'remedy_name' => $remedy->name,
                                'section' => $chunk['section'],
                                'chunk_index' => $chunkIndex,
                                'content' => $chunk['content'],
                                'content_hash' => $contentHash,
                                'language' => $sourceModel->language,
                                'metadata' => [
                                    'author' => $sourceModel->author,
                                    'edition' => $sourceModel->edition,
                                    'source_abbreviation' => $sourceModel->abbreviation,
                                    'legacy_row_id' => (int) $externalId,
                                    'legacy_mm_id' => (int) $externalMmId,
                                    'legacy_remedy_id' => (int) $externalRemedyId,
                                    'legacy_row' => [
                                        'id' => $externalId,
                                        'mm_id' => $externalMmId,
                                        'remedy_id' => $externalRemedyId,
                                    ],
                                    'legacy_row_raw' => $row,
                                ],
                            ]
                        );

                        $vector = $embedder->toPgVector($embedder->embed($chunk['content']));

                        DB::statement(
                            'UPDATE materia_medica_chunks SET embedding = ?::vector WHERE id = ?',
                            [$vector, $chunkModel->id]
                        );

                        $exists ? $updated++ : $created++;
                    }
                });
            } catch (\Throwable $exception) {
                $failed++;

                $errors[] = [
                    'row' => $index + 2,
                    'message' => $exception->getMessage(),
                    'data' => [
                        'id' => $row['id'] ?? null,
                        'mm_id' => $row['mm_id'] ?? null,
                        'remedy_id' => $row['remedy_id'] ?? null,
                    ],
                ];
            }

            if (! $dryRun && $processedRows % 100 === 0) {
                $legacyImport->update([
                    'processed_rows' => $processedRows,
                    'created_rows' => $created,
                    'updated_rows' => $updated,
                    'skipped_rows' => $skipped,
                    'failed_rows' => $failed,
                    'errors' => array_slice($errors, 0, 50),
                    'summary' => [
                        'chunks_created' => $created,
                        'chunks_updated' => $updated,
                    ],
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $summary = [
            'dry_run' => $dryRun,
            'rows_processed' => $processedRows,
            'chunks_created' => $created,
            'chunks_updated' => $updated,
            'rows_skipped' => $skipped,
            'rows_failed' => $failed,
        ];

        $legacyImport->update([
            'processed_rows' => $processedRows,
            'created_rows' => $created,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped,
            'failed_rows' => $failed,
            'errors' => array_slice($errors, 0, 50),
        ]);

        if ($failed > 0) {
            $legacyImport->markFailed('Some materia medica content rows could not be imported.', $errors);
        } else {
            $legacyImport->markCompleted($summary);
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Rows processed', $processedRows],
                ['Chunks created', $created],
                ['Chunks updated', $updated],
                ['Rows skipped', $skipped],
                ['Rows failed', $failed],
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

    private function importKey(string $source, int|string $externalId, int $chunkIndex): string
    {
        return "legacy:mm-content:{$source}:{$externalId}:{$chunkIndex}";
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }
}
