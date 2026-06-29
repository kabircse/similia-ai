<?php

namespace App\Services\Repertorization;

use App\Models\PatientVisit;
use App\Models\RepertorizationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EliminativeRepertorizationEngine
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

        $essentialRubrics = $caseRubrics
            ->filter(fn ($caseRubric) =>
                $caseRubric->is_essential ||
                $caseRubric->importance === 'essential'
            )
            ->values();

        if ($essentialRubrics->isEmpty()) {
            throw ValidationException::withMessages([
                'essential_rubrics' => [
                    'Please mark at least one rubric as essential before running eliminative repertorization.',
                ],
            ]);
        }

        $limit = (int) ($settings['limit'] ?? 50);
        $limit = max(1, min($limit, 100));

        $essentialRubricIds = $essentialRubrics
            ->pluck('id')
            ->values()
            ->all();

        $totalRubrics = $caseRubrics->count();
        $totalEssentialRubrics = $essentialRubrics->count();

        $remedies = [];

        foreach ($caseRubrics as $caseRubric) {
            $rubric = $caseRubric->rubric;

            if (! $rubric) {
                continue;
            }

            foreach ($rubric->remedies as $rubricRemedy) {
                $code = $rubricRemedy->remedy_code;
                $grade = (int) $rubricRemedy->grade;
                $weight = (int) $caseRubric->weight;
                $score = $weight * $grade;

                if (! isset($remedies[$code])) {
                    $remedies[$code] = [
                        'remedy_code' => $code,
                        'remedy_name' => $rubricRemedy->remedy_name,
                        'total_score' => 0,
                        'rubric_coverage' => 0,
                        'essential_coverage' => 0,
                        'grade_total' => 0,
                        'covered_case_rubric_ids' => [],
                        'covered_essential_rubric_ids' => [],
                        'supporting_rubrics' => [],
                    ];
                }

                $remedies[$code]['total_score'] += $score;
                $remedies[$code]['rubric_coverage'] += 1;
                $remedies[$code]['grade_total'] += $grade;
                $remedies[$code]['covered_case_rubric_ids'][] = $caseRubric->id;

                if (
                    $caseRubric->is_essential ||
                    $caseRubric->importance === 'essential'
                ) {
                    $remedies[$code]['essential_coverage'] += 1;
                    $remedies[$code]['covered_essential_rubric_ids'][] = $caseRubric->id;
                }

                $remedies[$code]['supporting_rubrics'][] = [
                    'case_rubric_id' => $caseRubric->id,
                    'repertory_rubric_id' => $rubric->id,
                    'rubric_path' => $rubric->rubric_path,
                    'rubric_text' => $rubric->rubric_text,
                    'symptom_type' => $caseRubric->symptom_type,
                    'importance' => $caseRubric->importance,
                    'is_essential' => (bool) $caseRubric->is_essential,
                    'rubric_weight' => $weight,
                    'remedy_grade' => $grade,
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

        $passedResults = collect($remedies)
            ->filter(function (array $remedy) use ($essentialRubricIds) {
                $coveredEssentialIds = array_unique($remedy['covered_essential_rubric_ids']);

                return empty(array_diff($essentialRubricIds, $coveredEssentialIds));
            })
            ->map(function (array $remedy) use (
                $importantRubrics,
                $totalRubrics,
                $totalEssentialRubrics
            ) {
                $coveredCaseRubricIds = array_unique($remedy['covered_case_rubric_ids']);

                $missingImportantRubrics = $importantRubrics
                    ->reject(fn ($caseRubric) => in_array($caseRubric->id, $coveredCaseRubricIds, true))
                    ->map(fn ($caseRubric) => [
                        'case_rubric_id' => $caseRubric->id,
                        'repertory_rubric_id' => $caseRubric->rubric?->id,
                        'rubric_path' => $caseRubric->rubric?->rubric_path,
                        'importance' => $caseRubric->importance,
                        'is_essential' => (bool) $caseRubric->is_essential,
                        'weight' => (int) $caseRubric->weight,
                    ])
                    ->values()
                    ->all();

                $coveragePercent = $totalRubrics > 0
                    ? round(($remedy['rubric_coverage'] / $totalRubrics) * 100, 2)
                    : 0;

                $essentialCoveragePercent = $totalEssentialRubrics > 0
                    ? round(($remedy['essential_coverage'] / $totalEssentialRubrics) * 100, 2)
                    : 0;

                $remedy['missing_important_rubrics'] = $missingImportantRubrics;
                $remedy['coverage_percent'] = $coveragePercent;
                $remedy['essential_coverage_percent'] = $essentialCoveragePercent;

                return $remedy;
            })
            ->sortBy([
                ['total_score', 'desc'],
                ['rubric_coverage', 'desc'],
                ['grade_total', 'desc'],
                ['remedy_name', 'asc'],
            ])
            ->values()
            ->take($limit)
            ->values();

        if ($passedResults->isEmpty()) {
            throw ValidationException::withMessages([
                'eliminative' => [
                    'No remedy covers all essential rubrics. Unmark one essential rubric or try weighted/cross repertorization.',
                ],
            ]);
        }

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
            $essentialRubrics,
            $passedResults,
            $snapshot,
            $essentialRubricIds,
            $remedies
        ) {
            $run = RepertorizationRun::create([
                'patient_visit_id' => $visit->id,
                'doctor_id' => $doctor->id,
                'method' => 'eliminative',
                'total_rubrics' => $caseRubrics->count(),
                'essential_rubrics_count' => $essentialRubrics->count(),
                'settings' => [
                    ...$settings,
                    'required_essential_rubric_ids' => $essentialRubricIds,
                    'total_remedies_before_elimination' => count($remedies),
                    'total_remedies_after_elimination' => $passedResults->count(),
                ],
                'selected_rubrics_snapshot' => $snapshot,
            ]);

            foreach ($passedResults as $index => $result) {
                $run->results()->create([
                    'remedy_code' => $result['remedy_code'],
                    'remedy_name' => $result['remedy_name'],
                    'total_score' => $result['total_score'],
                    'rubric_coverage' => $result['rubric_coverage'],
                    'essential_coverage' => $result['essential_coverage'],
                    'rank' => $index + 1,
                    'supporting_rubrics' => $result['supporting_rubrics'],
                    'missing_important_rubrics' => $result['missing_important_rubrics'],
                    'metrics' => [
                        'coverage_percent' => $result['coverage_percent'],
                        'essential_coverage_percent' => $result['essential_coverage_percent'],
                        'grade_total' => $result['grade_total'],
                        'eliminative_passed' => true,
                    ],
                ]);
            }

            return $run->fresh()->load('results');
        });
    }
}
