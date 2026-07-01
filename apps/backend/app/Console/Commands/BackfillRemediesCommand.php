<?php

namespace App\Console\Commands;

use App\Models\PatientPrescription;
use App\Models\Remedy;
use App\Services\Remedies\RemedyNormalizer;
use App\Services\Remedies\RemedyResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillRemediesCommand extends Command
{
    protected $signature = 'remedies:backfill-existing';

    protected $description = 'Backfill remedy master table from existing repertory, materia medica, and prescriptions';

    public function handle(
        RemedyNormalizer $normalizer,
        RemedyResolver $resolver
    ): int {
        $items = collect();

        foreach (DB::table('repertory_rubric_remedies')
            ->select('remedy_code', 'remedy_name')
            ->whereNotNull('remedy_name')
            ->distinct()
            ->cursor() as $row) {
            $items->push([
                'code' => $row->remedy_code,
                'name' => $row->remedy_name,
            ]);
        }

        foreach (DB::table('materia_medica_chunks')
            ->select('remedy_code', 'remedy_name')
            ->whereNotNull('remedy_name')
            ->distinct()
            ->cursor() as $row) {
            $items->push([
                'code' => $row->remedy_code,
                'name' => $row->remedy_name,
            ]);
        }

        foreach (DB::table('patient_prescriptions')
            ->select('remedy_code', 'remedy_name')
            ->whereNotNull('remedy_name')
            ->distinct()
            ->cursor() as $row) {
            $items->push([
                'code' => $row->remedy_code,
                'name' => $row->remedy_name,
            ]);
        }

        $items = $items
            ->filter(fn ($item) => ! empty($item['name']))
            ->unique(fn ($item) => $normalizer->codeFromAbbreviationOrName($item['code'] ?? null, $item['name']))
            ->values();

        $this->info('Backfilling remedies: '.$items->count());

        $createdOrUpdated = 0;

        foreach ($items as $item) {
            $code = $normalizer->codeFromAbbreviationOrName($item['code'] ?? null, $item['name']);

            if ($code === '') {
                continue;
            }

            $remedy = Remedy::query()->where('code', $code)->first() ?? new Remedy(['code' => $code]);

            if (! $remedy->exists) {
                $remedy->source = 'existing_app_data';
                $remedy->is_active = true;
            }

            $remedy->name = $remedy->name ?: $item['name'];
            $remedy->abbreviation = $remedy->abbreviation ?: ($item['code'] ?? null);
            $remedy->normalized_name = $normalizer->normalize($remedy->name);
            $remedy->normalized_abbreviation = $normalizer->normalize($remedy->abbreviation);
            $remedy->save();

            $resolver->syncDefaultAliases($remedy, $remedy->source ?: 'existing_app_data');
            $createdOrUpdated++;
        }

        $this->info("Remedies created/updated: {$createdOrUpdated}");
        $this->linkExistingRows();

        return self::SUCCESS;
    }

    private function linkExistingRows(): void
    {
        $this->info('Linking repertory rubric remedies...');
        $linkedRepertory = DB::affectingStatement(
            'UPDATE repertory_rubric_remedies target
             SET remedy_id = remedies.id
             FROM remedies
             WHERE target.remedy_id IS NULL
               AND target.remedy_code = remedies.code'
        );

        $this->info("Linked repertory rubric remedies: {$linkedRepertory}");

        $this->info('Linking materia medica chunks...');
        $linkedMateria = DB::affectingStatement(
            'UPDATE materia_medica_chunks target
             SET remedy_id = remedies.id
             FROM remedies
             WHERE target.remedy_id IS NULL
               AND target.remedy_code = remedies.code'
        );

        $this->info("Linked materia medica chunks: {$linkedMateria}");

        $this->info('Linking prescriptions...');

        PatientPrescription::query()
            ->whereNull('remedy_id')
            ->chunkById(500, function ($rows): void {
                $resolver = app(RemedyResolver::class);

                foreach ($rows as $row) {
                    $remedy = $resolver->findByText($row->remedy_code)
                        ?: $resolver->findByText($row->remedy_name);

                    if ($remedy) {
                        $row->update(['remedy_id' => $remedy->id]);
                    }
                }
            });

        $this->info('Existing rows linked.');
    }
}
