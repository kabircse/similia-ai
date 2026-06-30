<?php

namespace App\Console\Commands;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Models\LegacyImport;
use App\Services\Import\LegacyCsvReader;
use App\Services\Knowledge\BookSectionChunker;
use App\Services\Knowledge\SimpleTextEmbedding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyBookSectionsCommand extends Command
{
    protected $signature = 'import:legacy-book-sections
        {path : CSV path, for example storage/app/imports/legacy/book_sections.csv}
        {--source=legacy_sql : Source name}
        {--dry-run : Preview import without saving}
        {--limit=0 : Limit rows for testing}
        {--reimport : Delete previous chunks for each legacy section before importing}';

    protected $description = 'Import legacy book sections as embedded knowledge chunks';

    public function handle(
        LegacyCsvReader $csvReader,
        BookSectionChunker $chunker,
        SimpleTextEmbedding $embedder
    ): int {
        ini_set('memory_limit', '1024M');
        DB::connection()->disableQueryLog();

        $path = $this->resolvePath((string) $this->argument('path'));
        $source = (string) $this->option('source');
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
            'import_type' => 'book_sections',
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

        $this->info("Importing {$totalRows} book section rows from {$path}");
        $this->info("Source: {$source}");
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
                $externalId = $this->value($row, ['id', 'ID', 'section_id']);
                $bookCode = $this->value($row, ['book_code', 'code']);
                $sectionNo = (int) ($this->value($row, ['section_no', 'section_number', 'number']) ?: 0);
                $title = $this->value($row, ['title']) ?: 'Untitled Section';
                $body = $this->value($row, ['body', 'content', 'text']);
                $summary = $this->value($row, ['summary']);
                $sourceRef = $this->value($row, ['source_ref']);

                if (! $externalId || ! $bookCode || (! $body && ! $summary)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $knowledgeSource = KnowledgeSource::query()
                    ->where('source', $source)
                    ->where('code', $bookCode)
                    ->first();

                if (! $knowledgeSource) {
                    throw new \RuntimeException("Knowledge source not found for book_code {$bookCode}. Import books first.");
                }

                $chunks = $chunker->chunks(
                    body: $body ?? '',
                    title: $title,
                    summary: $summary
                );

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
                    $knowledgeSource,
                    $source,
                    $externalId,
                    $bookCode,
                    $sectionNo,
                    $title,
                    $summary,
                    $sourceRef,
                    $row,
                    $reimport,
                    &$created,
                    &$updated
                ): void {
                    if ($reimport) {
                        KnowledgeChunk::query()
                            ->where('source', $source)
                            ->where('external_id', (int) $externalId)
                            ->delete();
                    }

                    foreach ($chunks as $chunkIndex => $chunk) {
                        $contentHash = hash('sha256', $source.'|'.$externalId.'|'.$chunkIndex.'|'.$chunk['content']);
                        $exists = KnowledgeChunk::query()
                            ->where('source', $source)
                            ->where('external_id', (int) $externalId)
                            ->where('chunk_index', $chunkIndex)
                            ->exists();

                        $chunkModel = KnowledgeChunk::updateOrCreate(
                            [
                                'source' => $source,
                                'external_id' => (int) $externalId,
                                'chunk_index' => $chunkIndex,
                            ],
                            [
                                'knowledge_source_id' => $knowledgeSource->id,
                                'owner_user_id' => $knowledgeSource->owner_user_id,
                                'source_type' => $knowledgeSource->source_type,
                                'book_code' => $bookCode,
                                'section_no' => $sectionNo,
                                'title' => $chunk['title'] ?: $title,
                                'summary' => $summary,
                                'content' => $chunk['content'],
                                'content_hash' => $contentHash,
                                'language' => $knowledgeSource->language,
                                'source_ref' => $sourceRef ?: $knowledgeSource->source_ref,
                                'metadata' => [
                                    'book_title' => $knowledgeSource->title,
                                    'book_author' => $knowledgeSource->author,
                                    'edition' => $knowledgeSource->edition,
                                    'legacy_row_id' => (int) $externalId,
                                    'legacy_book_code' => $bookCode,
                                    'legacy_section_no' => $sectionNo,
                                    'legacy_row' => [
                                        'id' => $externalId,
                                        'book_code' => $bookCode,
                                        'section_no' => $sectionNo,
                                        'title' => $title,
                                    ],
                                    'legacy_row_raw' => $row,
                                ],
                            ]
                        );

                        $vector = $embedder->toPgVector($embedder->embed($chunk['content']));

                        DB::statement(
                            'UPDATE knowledge_chunks SET embedding = ?::vector WHERE id = ?',
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
                        'book_code' => $row['book_code'] ?? null,
                        'section_no' => $row['section_no'] ?? null,
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
            $legacyImport->markFailed('Some book section rows could not be imported.', $errors);
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

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path) || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }
}
