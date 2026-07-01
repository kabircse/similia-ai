<?php

namespace App\Console\Commands;

use App\Services\Knowledge\SimpleTextEmbedding;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SplFileObject;

class ImportKnowledgeCsvCommand extends Command
{
    protected $signature = 'knowledge:import-csv
        {--path= : Import directory. Defaults to database/imports}
        {--only=all : all, repertory, or materia-medica}
        {--fresh : Delete previously imported CSV rows before importing}
        {--limit= : Limit source records per large CSV for smoke tests}
        {--no-embeddings : Import materia medica chunks without embeddings}';

    protected $description = 'Import repertory and materia medica CSV files into the structured knowledge tables';

    private const RUBRIC_BATCH_SIZE = 1000;

    private const RUBRIC_REMEDY_BATCH_SIZE = 5000;

    private const MATERIA_BATCH_SIZE = 300;

    public function handle(SimpleTextEmbedding $embedder): int
    {
        ini_set('memory_limit', '1024M');

        DB::connection()->disableQueryLog();

        $path = (string) ($this->option('path') ?: database_path('imports'));
        $only = (string) $this->option('only');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if (! is_dir($path)) {
            $this->error("Import directory not found: {$path}");

            return self::FAILURE;
        }

        if (! in_array($only, ['all', 'repertory', 'materia-medica'], true)) {
            $this->error('Invalid --only value. Use all, repertory, or materia-medica.');

            return self::FAILURE;
        }

        $remedies = $this->attachMasterRemedyIds($this->loadRemedies($path));
        $this->info('Loaded '.count($remedies).' remedies from remedies.csv.');

        if ($only === 'all' || $only === 'repertory') {
            $repertories = $this->loadCsvById($path.DIRECTORY_SEPARATOR.'repertories.csv');
            $this->importRepertory($path, $remedies, $repertories, $limit);
        }

        if ($only === 'all' || $only === 'materia-medica') {
            $materiaMedicas = $this->loadCsvById($path.DIRECTORY_SEPARATOR.'materia_medica.csv');
            $this->importMateriaMedica($path, $remedies, $materiaMedicas, $embedder, $limit);
        }

        $this->info('Knowledge CSV import complete.');

        return self::SUCCESS;
    }

    private function importRepertory(
        string $path,
        array $remedies,
        array $repertories,
        ?int $limit
    ): void {
        if ($this->option('fresh')) {
            $this->line('Deleting previously imported repertory rows...');

            DB::table('repertory_rubric_remedies')
                ->where('import_key', 'like', 'csv:rubric-remedy:%')
                ->delete();

            DB::table('repertory_rubrics')
                ->where('import_key', 'like', 'csv:rubric:%')
                ->delete();
        }

        $rubricsPath = $path.DIRECTORY_SEPARATOR.'rubrics.csv';
        $remediesPath = $path.DIRECTORY_SEPARATOR.'rubric_remedies.csv';

        $this->requireFile($rubricsPath);
        $this->requireFile($remediesPath);

        $this->line('Importing rubrics...');

        $batch = [];
        $imported = 0;
        $now = now();

        foreach ($this->csvRows($rubricsPath) as $row) {
            if ($limit !== null && $imported >= $limit) {
                break;
            }

            $repertory = $repertories[(int) $row['repertory_id']] ?? [];
            $source = $this->sourceCode($repertory['abbreviation'] ?? 'repertory-'.$row['repertory_id']);
            $rawPath = $this->cleanText($row['text_en'] ?: $row['text_bn'] ?: '');
            $rubricPath = $this->normalizeRubricPath($rawPath);

            if ($rubricPath === '') {
                continue;
            }

            $batch[] = [
                'import_key' => 'csv:rubric:'.$row['id'],
                'source' => $source,
                'chapter' => $this->cleanText($row['chapter_name'] ?: $this->firstRubricPart($rubricPath)),
                'rubric_path' => $rubricPath,
                'rubric_text' => $this->lastRubricPart($rubricPath),
                'parent_id' => null,
                'page' => null,
                'metadata' => $this->json([
                    'imported_from' => 'database/imports/rubrics.csv',
                    'source_repertory_id' => (int) $row['repertory_id'],
                    'source_rubric_id' => (int) $row['id'],
                    'source_repertory_name' => $repertory['name'] ?? null,
                    'medicine_count' => $this->nullableInt($row['medicine_count'] ?? null),
                    'rubric_weight' => $this->nullableInt($row['rubric_weight'] ?? null),
                    'rubric_type' => $row['rubric_type'] ?: null,
                    'text_bn' => $row['text_bn'] ?: null,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $imported++;

            if (count($batch) >= self::RUBRIC_BATCH_SIZE) {
                $this->upsertRubrics($batch);
                $batch = [];

                if ($imported % 10000 === 0) {
                    $this->line("  Rubrics imported: {$imported}");
                }
            }
        }

        $this->upsertRubrics($batch);
        $this->info("Rubrics imported: {$imported}");

        $this->line('Building rubric ID map...');

        $rubricIdMap = [];

        foreach (DB::table('repertory_rubrics')
            ->select(['id', 'import_key'])
            ->where('import_key', 'like', 'csv:rubric:%')
            ->orderBy('id')
            ->cursor() as $rubric) {
            $sourceId = (int) str_replace('csv:rubric:', '', $rubric->import_key);
            $rubricIdMap[$sourceId] = (int) $rubric->id;
        }

        $this->line('Importing rubric remedies...');

        $batch = [];
        $batchKeys = [];
        $imported = 0;
        $skipped = 0;
        $scanned = 0;
        $now = now();

        foreach ($this->csvRows($remediesPath) as $row) {
            if ($limit !== null && $scanned >= $limit) {
                break;
            }

            $scanned++;

            $rubricLocalId = $rubricIdMap[(int) $row['rubric_id']] ?? null;
            $remedy = $remedies[(int) $row['remedy_id']] ?? null;

            if (! $rubricLocalId || ! $remedy) {
                $skipped++;

                continue;
            }

            $batchKey = $rubricLocalId.'|'.$remedy['code'];

            if (isset($batchKeys[$batchKey])) {
                $skipped++;

                continue;
            }

            $repertory = $repertories[(int) $row['repertory_id']] ?? [];

            $batch[] = [
                'import_key' => 'csv:rubric-remedy:'.$row['id'],
                'repertory_rubric_id' => $rubricLocalId,
                'remedy_id' => $remedy['remedy_id'],
                'remedy_code' => $remedy['code'],
                'remedy_name' => $remedy['name'],
                'grade' => max(1, min(4, (int) ($row['grade'] ?: 1))),
                'source' => $this->sourceCode($repertory['abbreviation'] ?? 'repertory-'.$row['repertory_id']),
                'metadata' => $this->json([
                    'imported_from' => 'database/imports/rubric_remedies.csv',
                    'source_rubric_remedy_id' => (int) $row['id'],
                    'source_rubric_id' => (int) $row['rubric_id'],
                    'source_remedy_id' => (int) $row['remedy_id'],
                    'source_repertory_id' => (int) $row['repertory_id'],
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $batchKeys[$batchKey] = true;

            $imported++;

            if (count($batch) >= self::RUBRIC_REMEDY_BATCH_SIZE) {
                $this->upsertRubricRemedies($batch);
                $batch = [];
                $batchKeys = [];

                if ($imported % 100000 === 0) {
                    $this->line("  Rubric remedies imported: {$imported}");
                }
            }
        }

        $this->upsertRubricRemedies($batch);
        $this->info("Rubric remedies imported: {$imported}; skipped: {$skipped}");
    }

    private function importMateriaMedica(
        string $path,
        array $remedies,
        array $materiaMedicas,
        SimpleTextEmbedding $embedder,
        ?int $limit
    ): void {
        if ($this->option('fresh')) {
            $this->line('Deleting previously imported materia medica chunks...');

            DB::table('materia_medica_chunks')
                ->where('import_key', 'like', 'csv:mm-content:%')
                ->delete();
        }

        $contentsPath = $path.DIRECTORY_SEPARATOR.'materia_medica_contents.csv';
        $this->requireFile($contentsPath);

        $this->line('Importing materia medica chunks...');

        $batch = [];
        $records = 0;
        $chunks = 0;
        $skipped = 0;
        $scanned = 0;
        $now = now();
        $withEmbeddings = ! $this->option('no-embeddings');

        foreach ($this->csvRows($contentsPath) as $row) {
            if ($limit !== null && $scanned >= $limit) {
                break;
            }

            $scanned++;

            $remedy = $remedies[(int) $row['remedy_id']] ?? null;
            $materiaMedica = $materiaMedicas[(int) $row['mm_id']] ?? [];
            $content = $this->cleanText($row['content'] ?? '');

            if (! $remedy || $content === '') {
                $skipped++;

                continue;
            }

            foreach ($this->chunkMateriaContent($content) as $index => $chunk) {
                $textForEmbedding = $remedy['name'].' '.$chunk['section'].' '.$chunk['content'];
                $record = [
                    'import_key' => 'csv:mm-content:'.$row['id'].':'.$index,
                    'source' => $this->sourceCode($materiaMedica['abbreviation'] ?? 'materia-'.$row['mm_id']),
                    'source_title' => $materiaMedica['name'] ?? null,
                    'remedy_id' => $remedy['remedy_id'],
                    'remedy_code' => $remedy['code'],
                    'remedy_name' => $remedy['name'],
                    'section' => $chunk['section'],
                    'content' => $chunk['content'],
                    'metadata' => $this->json([
                        'imported_from' => 'database/imports/materia_medica_contents.csv',
                        'source_content_id' => (int) $row['id'],
                        'source_materia_medica_id' => (int) $row['mm_id'],
                        'source_remedy_id' => (int) $row['remedy_id'],
                        'author' => $materiaMedica['author'] ?? null,
                        'edition' => $materiaMedica['edition'] ?? null,
                        'chunk_index' => $index,
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($withEmbeddings) {
                    $record['embedding'] = $embedder->toPgVector($embedder->embed($textForEmbedding));
                }

                $batch[] = $record;
                $chunks++;

                if (count($batch) >= self::MATERIA_BATCH_SIZE) {
                    $this->upsertMateriaChunks($batch, $withEmbeddings);
                    $batch = [];

                    if ($chunks % 5000 === 0) {
                        $this->line("  Materia medica records: {$records}; chunks: {$chunks}");
                    }
                }
            }

            $records++;
        }

        $this->upsertMateriaChunks($batch, $withEmbeddings);
        $this->info("Materia medica records imported: {$records}; chunks: {$chunks}; skipped: {$skipped}");
    }

    private function upsertRubrics(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        DB::table('repertory_rubrics')->upsert($rows, ['import_key'], [
            'source',
            'chapter',
            'rubric_path',
            'rubric_text',
            'metadata',
            'updated_at',
        ]);
    }

    private function upsertRubricRemedies(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        DB::table('repertory_rubric_remedies')->upsert($rows, ['repertory_rubric_id', 'remedy_code'], [
            'import_key',
            'repertory_rubric_id',
            'remedy_id',
            'remedy_code',
            'remedy_name',
            'grade',
            'source',
            'metadata',
            'updated_at',
        ]);
    }

    private function upsertMateriaChunks(array $rows, bool $withEmbeddings): void
    {
        if ($rows === []) {
            return;
        }

        $updates = [
            'source',
            'source_title',
            'remedy_id',
            'remedy_code',
            'remedy_name',
            'section',
            'content',
            'metadata',
            'updated_at',
        ];

        if ($withEmbeddings) {
            $updates[] = 'embedding';
        }

        DB::table('materia_medica_chunks')->upsert($rows, ['import_key'], $updates);
    }

    private function loadRemedies(string $path): array
    {
        $remediesPath = $path.DIRECTORY_SEPARATOR.'remedies.csv';
        $this->requireFile($remediesPath);

        $remedies = [];

        foreach ($this->csvRows($remediesPath) as $row) {
            $id = (int) $row['id'];
            $name = $this->cleanText($row['name'] ?? '');
            $abbreviation = $this->cleanText($row['abbreviation'] ?? '');

            if ($id <= 0 || $name === '') {
                continue;
            }

            $remedies[$id] = [
                'name' => $name,
                'code' => $this->normalizeRemedyCode($abbreviation ?: $name),
                'abbreviation' => $abbreviation,
                'remedy_id' => null,
            ];
        }

        return $remedies;
    }

    private function attachMasterRemedyIds(array $remedies): array
    {
        $idsByExternalId = DB::table('remedies')
            ->whereNotNull('external_id')
            ->pluck('id', 'external_id')
            ->all();

        $idsByCode = DB::table('remedies')
            ->pluck('id', 'code')
            ->all();

        foreach ($remedies as $legacyId => $remedy) {
            $remedies[$legacyId]['remedy_id'] = $idsByExternalId[$legacyId]
                ?? $idsByCode[$remedy['code']]
                ?? null;
        }

        return $remedies;
    }

    private function loadCsvById(string $path): array
    {
        $this->requireFile($path);

        $items = [];

        foreach ($this->csvRows($path) as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id > 0) {
                $items[$id] = $row;
            }
        }

        return $items;
    }

    private function csvRows(string $path): Generator
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $headers = null;

        foreach ($file as $row) {
            if ($row === false || $row === [null]) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(
                    fn ($header) => $this->cleanText((string) $header),
                    $row
                );

                continue;
            }

            $row = array_slice(array_pad($row, count($headers), null), 0, count($headers));

            yield array_combine($headers, $row);
        }
    }

    private function chunkMateriaContent(string $content): array
    {
        $content = preg_replace("/\r\n?/", "\n", $content) ?? $content;
        $blocks = preg_split("/\n{2,}/", trim($content)) ?: [];
        $chunks = [];
        $current = '';
        $section = 'general';
        $currentSection = $section;
        $maxLength = 1800;

        foreach ($blocks as $block) {
            $block = trim($block);

            if ($block === '') {
                continue;
            }

            if (preg_match('/^#{1,6}\s+(.+)$/u', $block, $matches)) {
                $section = mb_substr($this->cleanMarkdown($matches[1]), 0, 120);
            }

            if (mb_strlen($block) > $maxLength) {
                $this->pushMateriaChunk($chunks, $current, $currentSection);
                $current = '';

                foreach ($this->splitLongText($block, $maxLength) as $piece) {
                    $this->pushMateriaChunk($chunks, $piece, $section);
                }

                $currentSection = $section;

                continue;
            }

            if ($current !== '' && mb_strlen($current."\n\n".$block) > $maxLength) {
                $this->pushMateriaChunk($chunks, $current, $currentSection);
                $current = '';
                $currentSection = $section;
            }

            if ($current === '') {
                $currentSection = $section;
                $current = $block;
            } else {
                $current .= "\n\n".$block;
            }
        }

        $this->pushMateriaChunk($chunks, $current, $currentSection);

        return $chunks;
    }

    private function pushMateriaChunk(array &$chunks, string $content, string $section): void
    {
        $content = trim($content);

        if ($content === '') {
            return;
        }

        $chunks[] = [
            'section' => $section ?: 'general',
            'content' => $content,
        ];
    }

    private function splitLongText(string $text, int $maxLength): array
    {
        $pieces = [];
        $text = trim($text);

        while (mb_strlen($text) > $maxLength) {
            $slice = mb_substr($text, 0, $maxLength);
            $breakAt = max(
                mb_strrpos($slice, "\n") ?: 0,
                mb_strrpos($slice, '. ') ?: 0,
                mb_strrpos($slice, '; ') ?: 0
            );

            if ($breakAt < 400) {
                $breakAt = $maxLength;
            }

            $pieces[] = trim(mb_substr($text, 0, $breakAt));
            $text = trim(mb_substr($text, $breakAt));
        }

        if ($text !== '') {
            $pieces[] = $text;
        }

        return $pieces;
    }

    private function normalizeRubricPath(string $path): string
    {
        $path = preg_replace('/\s+/', ' ', trim($path)) ?? '';

        if ($path === '') {
            return '';
        }

        if (str_contains($path, '>')) {
            return $path;
        }

        $parts = array_values(array_filter(
            array_map('trim', explode(',', $path)),
            fn ($part) => $part !== ''
        ));

        return $parts === [] ? $path : implode(' > ', $parts);
    }

    private function firstRubricPart(string $path): string
    {
        $parts = array_values(array_filter(array_map('trim', explode('>', $path))));

        return $parts[0] ?? $path;
    }

    private function lastRubricPart(string $path): string
    {
        $parts = array_values(array_filter(array_map('trim', explode('>', $path))));

        return $parts === [] ? $path : end($parts);
    }

    private function normalizeRemedyCode(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['.', '_', ' '], ['', '-', '-'], $value);
        $value = preg_replace('/[^a-z0-9-]+/u', '', $value) ?? '';
        $value = preg_replace('/-+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function sourceCode(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace([' ', '_'], '-', $value);
        $value = preg_replace('/[^a-z0-9-]+/u', '', $value) ?? '';

        return trim($value, '-') ?: 'csv';
    }

    private function cleanText(?string $value): string
    {
        $value = (string) $value;
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return trim($value);
    }

    private function cleanMarkdown(string $value): string
    {
        $value = preg_replace('/[*_`#]+/', '', $value) ?? $value;

        return trim($value);
    }

    private function nullableInt(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return (int) $value;
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function requireFile(string $path): void
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Required CSV file not found: {$path}");
        }
    }
}
