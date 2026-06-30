<?php

namespace App\Console\Commands;

use App\Models\KnowledgeSource;
use App\Models\LegacyImport;
use App\Services\Import\LegacyCsvReader;
use App\Services\Knowledge\KnowledgeTypeClassifier;
use Illuminate\Console\Command;

class ImportLegacyBooksCommand extends Command
{
    protected $signature = 'import:legacy-books
        {path : CSV path, for example storage/app/imports/legacy/books.csv}
        {--source=legacy_sql : Source name}
        {--visibility=global_demo : private, clinic, global_demo}
        {--dry-run : Preview import without saving}';

    protected $description = 'Import legacy books CSV into knowledge_sources table';

    public function handle(
        LegacyCsvReader $csvReader,
        KnowledgeTypeClassifier $classifier
    ): int {
        $path = $this->resolvePath((string) $this->argument('path'));
        $source = (string) $this->option('source');
        $visibility = (string) $this->option('visibility');
        $dryRun = (bool) $this->option('dry-run');

        if (! in_array($visibility, ['private', 'clinic', 'global_demo'], true)) {
            $this->error('Invalid --visibility value. Use private, clinic, or global_demo.');

            return self::FAILURE;
        }

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $totalRows = $csvReader->countDataRows($path);
        $legacyImport = LegacyImport::create([
            'import_type' => 'books',
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

        $this->info("Importing {$totalRows} books from {$path}");
        $this->info("Source: {$source}");
        $this->info("Visibility: {$visibility}");
        $this->info($dryRun ? 'Mode: dry-run' : 'Mode: import');

        if (! $dryRun) {
            $legacyImport->markRunning($totalRows);
        }

        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        foreach ($csvReader->rows($path) as $index => $row) {
            try {
                $externalId = $this->value($row, ['id', 'ID']);
                $code = $this->value($row, ['code', 'book_code']);
                $title = $this->value($row, ['title', 'name']);
                $author = $this->value($row, ['author']);
                $language = $this->value($row, ['language']);
                $edition = $this->value($row, ['edition']);
                $sourceRef = $this->value($row, ['source_ref']);

                if (! $code || ! $title) {
                    throw new \InvalidArgumentException('Book code and title are required.');
                }

                $sourceType = $this->value($row, ['source_type'])
                    ?: $classifier->classify($code, $title, $author);

                $lookup = $externalId
                    ? ['source' => $source, 'external_id' => (int) $externalId]
                    : ['source' => $source, 'code' => $code];

                $payload = [
                    'owner_user_id' => null,
                    'source' => $source,
                    'external_id' => $externalId ? (int) $externalId : null,
                    'code' => $code,
                    'title' => $title,
                    'author' => $author,
                    'source_type' => $sourceType,
                    'language' => $language,
                    'edition' => $edition,
                    'source_ref' => $sourceRef,
                    'visibility' => $visibility,
                    'is_active' => true,
                    'metadata' => [
                        'legacy_row' => $row,
                    ],
                ];

                $exists = KnowledgeSource::query()->where($lookup)->exists();

                if (! $dryRun) {
                    KnowledgeSource::updateOrCreate($lookup, $payload);
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
            $legacyImport->markFailed('Some book rows could not be imported.', $errors);
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
