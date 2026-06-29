<?php

namespace App\Services\Repertorization;

use App\Models\PatientVisit;
use App\Models\RepertorizationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WeightedRepertorizationEngine
{
    public function run(PatientVisit $visit, User $doctor, array $settings = []): RepertorizationRun
    {
        $caseRubrics = $visit->caseRubrics()
            ->with(['rubric.remedies'])
            ->get();

        if ($caseRubrics->isEmpty()) {
            throw ValidationException::withMessages([
                'rubrics' => ['Please select at least one rubric before repertorization.'],
            ]);
        }

        $limit = (int) ($settings['limit'] ?? 50);
        $limit = max(1, min($limit, 100));

        $remedies = [];

        foreach ($caseRubrics as $caseRubric) {
            $rubric = $caseRubric->rubric;

            if (! $rubric) {
                continue;
            }

            foreach ($rubric->remedies as $rubricRemedy) {
                $code = $rubricRemedy->remedy_code;
                $score = (int) $caseRubric->weight * (int) $rubricRemedy->grade;

                if (! isset($remedies[$code])) {
                    $remedies[$code] = [
                        'remedy_code' => $code,
                        'remedy_name' => $rubricRemedy->remedy_name,
                        'total_score' => 0,
                        'rubric_coverage' => 0,
                        'essential_coverage' => 0,
                        'supporting_rubrics' => [],
                    ];
                }

                $remedies[$code]['total_score'] += $score;
                $remedies[$code]['rubric_coverage'] += 1;

                if ($caseRubric->is_essential) {
                    $remedies[$code]['essential_coverage'] += 1;
                }

                $remedies[$code]['supporting_rubrics'][] = [
                    'case_rubric_id' => $caseRubric->id,
                    'repertory_rubric_id' => $rubric->id,
                    'rubric_path' => $rubric->rubric_path,
                    'rubric_text' => $rubric->rubric_text,
                    'symptom_type' => $caseRubric->symptom_type,
                    'importance' => $caseRubric->importance,
                    'is_essential' => $caseRubric->is_essential,
                    'rubric_weight' => (int) $caseRubric->weight,
                    'remedy_grade' => (int) $rubricRemedy->grade,
                    'score' => $score,
                ];
            }
        }

        if (empty($remedies)) {
            throw ValidationException::withMessages([
                'rubrics' => ['Selected rubrics do not have remedy data.'],
            ]);
        }

        $importantRubrics = $caseRubrics
            ->filter(fn ($caseRubric) =>
                $caseRubric->is_essential ||
                in_array($caseRubric->importance, ['essential', 'important'], true)
            )
            ->values();

        $rankedResults = collect($remedies)
            ->map(function (array $remedy) use ($importantRubrics) {
                $coveredCaseRubricIds = collect($remedy['supporting_rubrics'])
                    ->pluck('case_rubric_id')
                    ->all();

                $missingImportantRubrics = $importantRubrics
                    ->reject(fn ($caseRubric) => in_array($caseRubric->id, $coveredCaseRubricIds, true))
                    ->map(fn ($caseRubric) => [
                        'case_rubric_id' => $caseRubric->id,
                        'repertory_rubric_id' => $caseRubric->rubric?->id,
                        'rubric_path' => $caseRubric->rubric?->rubric_path,
                        'importance' => $caseRubric->importance,
                        'is_essential' => $caseRubric->is_essential,
                        'weight' => (int) $caseRubric->weight,
                    ])
                    ->values()
                    ->all();

                $remedy['missing_important_rubrics'] = $missingImportantRubrics;

                return $remedy;
            })
            ->sortBy([
                ['total_score', 'desc'],
                ['essential_coverage', 'desc'],
                ['rubric_coverage', 'desc'],
                ['remedy_name', 'asc'],
            ])
            ->values()
            ->take($limit)
            ->values();

        $snapshot = $caseRubrics
            ->map(fn ($caseRubric) => [
                'case_rubric_id' => $caseRubric->id,
                'repertory_rubric_id' => $caseRubric->repertory_rubric_id,
                'rubric_path' => $caseRubric->rubric?->rubric_path,
                'symptom_type' => $caseRubric->symptom_type,
                'importance' => $caseRubric->importance,
                'weight' => (int) $caseRubric->weight,
                'is_essential' => (bool) $caseRubric->is_essential,
            ])
            ->values()
            ->all();

        return DB::transaction(function () use (
            $visit,
            $doctor,
            $settings,
            $caseRubrics,
            $rankedResults,
            $snapshot
        ) {
            $run = RepertorizationRun::create([
                'patient_visit_id' => $visit->id,
                'doctor_id' => $doctor->id,
                'method' => 'weighted',
                'total_rubrics' => $caseRubrics->count(),
                'essential_rubrics_count' => $caseRubrics->where('is_essential', true)->count(),
                'settings' => $settings,
                'selected_rubrics_snapshot' => $snapshot,
            ]);

            foreach ($rankedResults as $index => $result) {
                $run->results()->create([
                    'remedy_code' => $result['remedy_code'],
                    'remedy_name' => $result['remedy_name'],
                    'total_score' => $result['total_score'],
                    'rubric_coverage' => $result['rubric_coverage'],
                    'essential_coverage' => $result['essential_coverage'],
                    'rank' => $index + 1,
                    'supporting_rubrics' => $result['supporting_rubrics'],
                    'missing_important_rubrics' => $result['missing_important_rubrics'],
                ]);
            }

            return $run->fresh()->load('results');
        });
    }
}