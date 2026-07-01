<?php

namespace App\Services\Search;

use App\Models\ClinicReportRun;
use App\Models\ClinicReportSection;
use App\Models\FollowUpAnalysisRun;
use App\Models\Patient;
use App\Models\PatientHandoutRun;
use App\Models\PatientHandoutSection;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\PotencyGuidanceOption;
use App\Models\PotencyGuidanceRun;
use App\Models\PrescriptionReviewCheck;
use App\Models\PrescriptionReviewRun;
use App\Models\RemedyRelationshipFinding;
use App\Models\RemedyRelationshipRun;
use App\Models\RemedySuggestionItem;
use App\Models\RemedySuggestionRun;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdvancedSearchService
{
    public function search(array $filters, int $userId, string $role): array
    {
        $q = trim((string) $filters['q']);
        $types = $filters['types'] ?? $this->defaultTypes();
        $limit = (int) ($filters['limit'] ?? 50);

        $context = [
            'q' => $q,
            'like' => '%'.addcslashes($q, '%_\\').'%',
            'user_id' => $userId,
            'role' => $role,
            'doctor_id' => $role === 'admin' ? null : $userId,
            'patient_id' => $filters['patient_id'] ?? null,
            'visit_id' => $filters['visit_id'] ?? null,
            'date_from' => ! empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : null,
            'date_to' => ! empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : null,
            'limit' => $limit,
        ];

        $results = collect();

        foreach ($types as $type) {
            $results = $results->merge(match ($type) {
                'patients' => $this->patients($context),
                'visits' => $this->visits($context),
                'prescriptions' => $this->prescriptions($context),
                'remedy_suggestions' => $this->remedySuggestions($context),
                'follow_up_analyses' => $this->followUpAnalyses($context),
                'potency_guidance' => $this->potencyGuidance($context),
                'remedy_relationships' => $this->remedyRelationships($context),
                'prescription_reviews' => $this->prescriptionReviews($context),
                'patient_handouts' => $this->patientHandouts($context),
                'clinic_reports' => $this->clinicReports($context),
                default => collect(),
            });
        }

        $sorted = $results
            ->sort(fn (array $a, array $b) => $this->compareResults($a, $b))
            ->take($limit)
            ->values();

        return [
            'query' => $q,
            'types' => array_values($types),
            'total' => $sorted->count(),
            'results' => $sorted->all(),
        ];
    }

    private function defaultTypes(): array
    {
        return [
            'patients',
            'visits',
            'prescriptions',
            'remedy_suggestions',
            'follow_up_analyses',
            'potency_guidance',
            'remedy_relationships',
            'prescription_reviews',
            'patient_handouts',
            'clinic_reports',
        ];
    }

    private function patients(array $context): Collection
    {
        return $this->applyClinicalScope(
            Patient::query(),
            $context,
            patientColumn: 'id',
            visitColumn: null
        )
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'name',
                    'phone',
                    'address',
                    'occupation',
                    'notes',
                ]);
            })
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Patient $patient) => [
                'type' => 'patients',
                'label' => 'Patient',
                'id' => $patient->id,
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'visit_id' => null,
                'title' => $patient->name,
                'subtitle' => $this->joinParts([
                    $patient->phone,
                    $patient->gender,
                    $patient->age_years ? $patient->age_years.' years' : null,
                ]),
                'snippet' => $this->snippet([$patient->notes, $patient->address, $patient->occupation], $context['q']),
                'url' => "/patients/{$patient->id}",
                'created_at' => $patient->created_at?->toISOString(),
                'score' => $this->score([$patient->name, $patient->phone, $patient->notes], $context['q']),
                'metadata' => [
                    'gender' => $patient->gender,
                    'age_years' => $patient->age_years,
                ],
            ]);
    }

    private function visits(array $context): Collection
    {
        return $this->applyClinicalScope(
            PatientVisit::query()->with('patient:id,name,doctor_id'),
            $context
        )
            ->when($context['date_from'], fn (Builder $query) => $query->where('visit_date', '>=', $context['date_from']->toDateString()))
            ->when($context['date_to'], fn (Builder $query) => $query->where('visit_date', '<=', $context['date_to']->toDateString()))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'chief_complaint',
                    'raw_case_text',
                    'doctor_notes',
                ], [
                    'case_sections::text',
                    'missing_questions::text',
                    'red_flags::text',
                ]);
            })
            ->latest('visit_date')
            ->limit(20)
            ->get()
            ->map(fn (PatientVisit $visit) => [
                'type' => 'visits',
                'label' => 'Visit',
                'id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'patient_name' => $visit->patient?->name,
                'visit_id' => $visit->id,
                'title' => $visit->chief_complaint ?: 'Patient visit',
                'subtitle' => $this->joinParts([
                    $visit->patient?->name,
                    $visit->visit_type,
                    $visit->visit_date?->toDateString(),
                ]),
                'snippet' => $this->snippet([$visit->raw_case_text, $visit->doctor_notes, $visit->case_sections], $context['q']),
                'url' => "/patients/{$visit->patient_id}/visits/{$visit->id}",
                'created_at' => $visit->created_at?->toISOString(),
                'score' => $this->score([$visit->chief_complaint, $visit->raw_case_text, $visit->doctor_notes, $visit->case_sections], $context['q']),
                'metadata' => [
                    'visit_type' => $visit->visit_type,
                    'status' => $visit->status,
                ],
            ]);
    }

    private function prescriptions(array $context): Collection
    {
        return $this->applyClinicalScope(
            PatientPrescription::query()->with('patient:id,name,doctor_id'),
            $context
        )
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'remedy_name',
                    'remedy_code',
                    'potency',
                    'repetition',
                    'dose_instruction',
                    'reason',
                    'advice',
                    'food_lifestyle_note',
                ]);
            })
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (PatientPrescription $prescription) => [
                'type' => 'prescriptions',
                'label' => 'Prescription',
                'id' => $prescription->id,
                'patient_id' => $prescription->patient_id,
                'patient_name' => $prescription->patient?->name,
                'visit_id' => $prescription->patient_visit_id,
                'title' => $this->joinParts([$prescription->remedy_name, $prescription->potency], ' ') ?: 'Prescription',
                'subtitle' => $this->joinParts([$prescription->patient?->name, $prescription->status, $prescription->follow_up_date?->toDateString()]),
                'snippet' => $this->snippet([$prescription->reason, $prescription->advice, $prescription->dose_instruction], $context['q']),
                'url' => "/patients/{$prescription->patient_id}/visits/{$prescription->patient_visit_id}",
                'created_at' => $prescription->created_at?->toISOString(),
                'score' => $this->score([$prescription->remedy_name, $prescription->remedy_code, $prescription->potency, $prescription->reason], $context['q']),
                'metadata' => [
                    'remedy_code' => $prescription->remedy_code,
                    'potency' => $prescription->potency,
                ],
            ]);
    }

    private function remedySuggestions(array $context): Collection
    {
        $runs = $this->applyClinicalScope(
            RemedySuggestionRun::query()->with('patient:id,name,doctor_id'),
            $context
        )
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'method',
                    'safety_note',
                    'error_message',
                ], [
                    'case_snapshot::text',
                    'selected_rubrics_snapshot::text',
                    'retrieved_sources::text',
                ]);
            })
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (RemedySuggestionRun $run) => $this->visitResult(
                type: 'remedy_suggestions',
                label: 'Remedy Suggestion',
                id: $run->id,
                patientId: $run->patient_id,
                patientName: $run->patient?->name,
                visitId: $run->patient_visit_id,
                title: 'Remedy suggestion run',
                subtitle: $this->joinParts([$run->patient?->name, $run->method, $run->status]),
                snippet: $this->snippet([$run->case_snapshot, $run->safety_note], $context['q']),
                createdAt: $run->created_at?->toISOString(),
                score: $this->score([$run->method, $run->case_snapshot, $run->safety_note], $context['q']),
                metadata: ['status' => $run->status]
            ));

        $items = RemedySuggestionItem::query()
            ->with('run.patient:id,name,doctor_id')
            ->whereHas('run', fn (Builder $query) => $this->applyClinicalScope($query, $context))
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'remedy_name',
                    'remedy_code',
                    'summary',
                ], [
                    'matching_points::text',
                    'differentiating_points::text',
                    'missing_questions::text',
                    'medical_safety_notes::text',
                    'source_chunks::text',
                ]);
            })
            ->latest()
            ->limit(16)
            ->get()
            ->map(function (RemedySuggestionItem $item) use ($context) {
                $run = $item->run;

                return $this->visitResult(
                    type: 'remedy_suggestions',
                    label: 'Remedy Suggestion',
                    id: $item->id,
                    patientId: $run?->patient_id,
                    patientName: $run?->patient?->name,
                    visitId: $run?->patient_visit_id,
                    title: $this->joinParts([$item->remedy_name, 'suggestion'], ' '),
                    subtitle: $this->joinParts([$run?->patient?->name, 'rank '.$item->rank, 'confidence '.$item->confidence_score]),
                    snippet: $this->snippet([$item->summary, $item->matching_points, $item->differentiating_points], $context['q']),
                    createdAt: $item->created_at?->toISOString(),
                    score: $this->score([$item->remedy_name, $item->remedy_code, $item->summary, $item->matching_points], $context['q']),
                    metadata: [
                        'rank' => $item->rank,
                        'confidence_score' => $item->confidence_score,
                    ]
                );
            });

        return $runs->merge($items);
    }

    private function followUpAnalyses(array $context): Collection
    {
        return $this->applyClinicalScope(
            FollowUpAnalysisRun::query()->with('patient:id,name,doctor_id'),
            $context
        )
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'response_level',
                    'analysis_summary',
                    'remedy_response_assessment',
                    'safety_note',
                ], [
                    'improvement_points::text',
                    'worsening_points::text',
                    'unchanged_points::text',
                    'new_symptoms::text',
                    'old_symptoms_returned::text',
                    'possible_aggravation_signs::text',
                    'red_flags::text',
                    'doctor_review_points::text',
                    'recommended_next_steps::text',
                ]);
            })
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (FollowUpAnalysisRun $run) => $this->visitResult(
                type: 'follow_up_analyses',
                label: 'Follow-up Analysis',
                id: $run->id,
                patientId: $run->patient_id,
                patientName: $run->patient?->name,
                visitId: $run->patient_visit_id,
                title: $run->analysis_summary ? Str::limit($run->analysis_summary, 80) : 'Follow-up analysis',
                subtitle: $this->joinParts([$run->patient?->name, $run->response_level, 'score '.$run->progress_score]),
                snippet: $this->snippet([$run->analysis_summary, $run->remedy_response_assessment, $run->new_symptoms, $run->red_flags], $context['q']),
                createdAt: $run->created_at?->toISOString(),
                score: $this->score([$run->analysis_summary, $run->remedy_response_assessment, $run->new_symptoms, $run->red_flags], $context['q']),
                metadata: [
                    'response_level' => $run->response_level,
                    'progress_score' => $run->progress_score,
                ]
            ));
    }

    private function potencyGuidance(array $context): Collection
    {
        $runs = $this->applyClinicalScope(
            PotencyGuidanceRun::query()->with('patient:id,name,doctor_id'),
            $context
        )
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'remedy_name',
                    'remedy_code',
                    'case_phase',
                    'vitality_level',
                    'sensitivity_level',
                    'pathology_depth',
                    'guidance_summary',
                    'repetition_guidance',
                    'wait_and_watch_guidance',
                    'aggravation_guidance',
                    'safety_note',
                ], [
                    'cautions::text',
                    'follow_up_questions::text',
                    'doctor_review_points::text',
                ]);
            })
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (PotencyGuidanceRun $run) => $this->visitResult(
                type: 'potency_guidance',
                label: 'Potency Guidance',
                id: $run->id,
                patientId: $run->patient_id,
                patientName: $run->patient?->name,
                visitId: $run->patient_visit_id,
                title: $this->joinParts(['Potency guidance', $run->remedy_name], ': '),
                subtitle: $this->joinParts([$run->patient?->name, $run->case_phase, $run->vitality_level]),
                snippet: $this->snippet([$run->guidance_summary, $run->repetition_guidance, $run->cautions], $context['q']),
                createdAt: $run->created_at?->toISOString(),
                score: $this->score([$run->remedy_name, $run->guidance_summary, $run->cautions], $context['q']),
                metadata: ['case_phase' => $run->case_phase]
            ));

        $options = PotencyGuidanceOption::query()
            ->with('run.patient:id,name,doctor_id')
            ->whereHas('run', fn (Builder $query) => $this->applyClinicalScope($query, $context))
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'potency_range',
                    'potency_label',
                    'rationale',
                    'repetition_note',
                    'caution',
                ], [
                    'source_chunks::text',
                ]);
            })
            ->latest()
            ->limit(12)
            ->get()
            ->map(function (PotencyGuidanceOption $option) use ($context) {
                $run = $option->run;

                return $this->visitResult(
                    type: 'potency_guidance',
                    label: 'Potency Guidance',
                    id: $option->id,
                    patientId: $run?->patient_id,
                    patientName: $run?->patient?->name,
                    visitId: $run?->patient_visit_id,
                    title: $this->joinParts([$option->potency_label ?: $option->potency_range, 'option'], ' '),
                    subtitle: $this->joinParts([$run?->patient?->name, $run?->remedy_name, 'rank '.$option->rank]),
                    snippet: $this->snippet([$option->rationale, $option->repetition_note, $option->caution], $context['q']),
                    createdAt: $option->created_at?->toISOString(),
                    score: $this->score([$option->potency_range, $option->potency_label, $option->rationale, $option->caution], $context['q']),
                    metadata: ['rank' => $option->rank]
                );
            });

        return $runs->merge($options);
    }

    private function remedyRelationships(array $context): Collection
    {
        $runs = $this->applyClinicalScope(
            RemedyRelationshipRun::query()->with('patient:id,name,doctor_id'),
            $context
        )
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'primary_remedy_name',
                    'comparison_remedy_name',
                    'purpose',
                    'relationship_summary',
                    'sequence_guidance',
                    'antidote_guidance',
                    'inimical_warning',
                    'complementary_note',
                    'safety_note',
                ], [
                    'cautions::text',
                    'doctor_review_points::text',
                    'suggested_questions::text',
                ]);
            })
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (RemedyRelationshipRun $run) => $this->visitResult(
                type: 'remedy_relationships',
                label: 'Remedy Relationship',
                id: $run->id,
                patientId: $run->patient_id,
                patientName: $run->patient?->name,
                visitId: $run->patient_visit_id,
                title: $this->joinParts([$run->primary_remedy_name, $run->comparison_remedy_name], ' and ') ?: 'Remedy relationship',
                subtitle: $this->joinParts([$run->patient?->name, $run->purpose, $run->status]),
                snippet: $this->snippet([$run->relationship_summary, $run->sequence_guidance, $run->inimical_warning], $context['q']),
                createdAt: $run->created_at?->toISOString(),
                score: $this->score([$run->primary_remedy_name, $run->comparison_remedy_name, $run->relationship_summary], $context['q']),
                metadata: ['purpose' => $run->purpose]
            ));

        $findings = RemedyRelationshipFinding::query()
            ->with('run.patient:id,name,doctor_id')
            ->whereHas('run', fn (Builder $query) => $this->applyClinicalScope($query, $context))
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'related_remedy_name',
                    'relationship_type',
                    'summary',
                    'clinical_note',
                    'caution',
                ], [
                    'evidence::text',
                    'source_chunks::text',
                ]);
            })
            ->latest()
            ->limit(12)
            ->get()
            ->map(function (RemedyRelationshipFinding $finding) use ($context) {
                $run = $finding->run;

                return $this->visitResult(
                    type: 'remedy_relationships',
                    label: 'Remedy Relationship',
                    id: $finding->id,
                    patientId: $run?->patient_id,
                    patientName: $run?->patient?->name,
                    visitId: $run?->patient_visit_id,
                    title: $this->joinParts([$finding->related_remedy_name, $finding->relationship_type], ' - '),
                    subtitle: $this->joinParts([$run?->patient?->name, 'rank '.$finding->rank]),
                    snippet: $this->snippet([$finding->summary, $finding->clinical_note, $finding->caution, $finding->evidence], $context['q']),
                    createdAt: $finding->created_at?->toISOString(),
                    score: $this->score([$finding->related_remedy_name, $finding->relationship_type, $finding->summary, $finding->evidence], $context['q']),
                    metadata: ['relationship_type' => $finding->relationship_type]
                );
            });

        return $runs->merge($findings);
    }

    private function prescriptionReviews(array $context): Collection
    {
        $runs = $this->applyClinicalScope(
            PrescriptionReviewRun::query()->with('patient:id,name,doctor_id'),
            $context
        )
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'remedy_name',
                    'potency',
                    'repetition',
                    'review_status',
                    'review_summary',
                    'decision_guidance',
                    'risk_summary',
                    'safety_note',
                ], [
                    'red_flags::text',
                    'missing_information::text',
                    'doctor_review_points::text',
                    'recommended_actions::text',
                ]);
            })
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (PrescriptionReviewRun $run) => $this->visitResult(
                type: 'prescription_reviews',
                label: 'Prescription Review',
                id: $run->id,
                patientId: $run->patient_id,
                patientName: $run->patient?->name,
                visitId: $run->patient_visit_id,
                title: $this->joinParts(['Prescription review', $run->review_status], ': '),
                subtitle: $this->joinParts([$run->patient?->name, $run->remedy_name, 'score '.$run->safety_score]),
                snippet: $this->snippet([$run->review_summary, $run->risk_summary, $run->red_flags, $run->missing_information], $context['q']),
                createdAt: $run->created_at?->toISOString(),
                score: $this->score([$run->remedy_name, $run->review_status, $run->review_summary, $run->risk_summary], $context['q']),
                metadata: ['review_status' => $run->review_status]
            ));

        $checks = PrescriptionReviewCheck::query()
            ->with('run.patient:id,name,doctor_id')
            ->whereHas('run', fn (Builder $query) => $this->applyClinicalScope($query, $context))
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'check_key',
                    'category',
                    'severity',
                    'status',
                    'title',
                    'description',
                    'ai_assessment',
                    'doctor_note',
                ], [
                    'evidence::text',
                ]);
            })
            ->latest()
            ->limit(12)
            ->get()
            ->map(function (PrescriptionReviewCheck $check) use ($context) {
                $run = $check->run;

                return $this->visitResult(
                    type: 'prescription_reviews',
                    label: 'Prescription Review',
                    id: $check->id,
                    patientId: $run?->patient_id,
                    patientName: $run?->patient?->name,
                    visitId: $run?->patient_visit_id,
                    title: $check->title,
                    subtitle: $this->joinParts([$run?->patient?->name, $check->category, $check->severity]),
                    snippet: $this->snippet([$check->description, $check->ai_assessment, $check->doctor_note, $check->evidence], $context['q']),
                    createdAt: $check->created_at?->toISOString(),
                    score: $this->score([$check->title, $check->description, $check->ai_assessment, $check->evidence], $context['q']),
                    metadata: [
                        'severity' => $check->severity,
                        'status' => $check->status,
                    ]
                );
            });

        return $runs->merge($checks);
    }

    private function patientHandouts(array $context): Collection
    {
        $runs = $this->applyClinicalScope(
            PatientHandoutRun::query()->with('patient:id,name,doctor_id'),
            $context
        )
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'title',
                    'patient_summary',
                    'medicine_instruction',
                    'diet_lifestyle_instruction',
                    'follow_up_instruction',
                    'warning_instruction',
                    'footer_note',
                    'safety_note',
                ], [
                    'warning_signs::text',
                    'do_and_dont::text',
                    'case_snapshot::text',
                    'prescription_snapshot::text',
                ]);
            })
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (PatientHandoutRun $run) => $this->handoutResult(
                id: $run->id,
                patientId: $run->patient_id,
                patientName: $run->patient?->name,
                visitId: $run->patient_visit_id,
                title: $run->title ?: 'Patient handout',
                subtitle: $this->joinParts([$run->patient?->name, $run->handout_type, $run->status]),
                snippet: $this->snippet([$run->patient_summary, $run->medicine_instruction, $run->warning_instruction], $context['q']),
                createdAt: $run->created_at?->toISOString(),
                score: $this->score([$run->title, $run->patient_summary, $run->medicine_instruction, $run->warning_instruction], $context['q']),
                metadata: ['status' => $run->status]
            ));

        $sections = PatientHandoutSection::query()
            ->with('run.patient:id,name,doctor_id')
            ->whereHas('run', fn (Builder $query) => $this->applyClinicalScope($query, $context))
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'section_key',
                    'category',
                    'title',
                    'content',
                ]);
            })
            ->latest()
            ->limit(12)
            ->get()
            ->map(function (PatientHandoutSection $section) use ($context) {
                $run = $section->run;

                return $this->handoutResult(
                    id: $section->id,
                    patientId: $run?->patient_id,
                    patientName: $run?->patient?->name,
                    visitId: $run?->patient_visit_id,
                    title: $section->title,
                    subtitle: $this->joinParts([$run?->patient?->name, $section->category]),
                    snippet: $this->snippet([$section->content], $context['q']),
                    createdAt: $section->created_at?->toISOString(),
                    score: $this->score([$section->title, $section->content], $context['q']),
                    metadata: ['section_key' => $section->section_key],
                    runId: $run?->id
                );
            });

        return $runs->merge($sections);
    }

    private function clinicReports(array $context): Collection
    {
        if ($context['patient_id'] || $context['visit_id']) {
            return collect();
        }

        $runs = $this->applyReportScope(ClinicReportRun::query(), $context)
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'title',
                    'executive_summary',
                    'clinical_activity_summary',
                    'outcome_summary',
                    'remedy_summary',
                    'safety_summary',
                    'finance_summary',
                    'follow_up_summary',
                    'safety_note',
                ], [
                    'recommendations::text',
                    'limitations::text',
                    'key_metrics::text',
                ]);
            })
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (ClinicReportRun $run) => $this->reportResult(
                id: $run->id,
                title: $run->title ?: 'Clinic report',
                subtitle: $this->joinParts([$run->period_start?->toDateString(), $run->period_end?->toDateString(), $run->report_type]),
                snippet: $this->snippet([$run->executive_summary, $run->safety_summary, $run->recommendations], $context['q']),
                createdAt: $run->created_at?->toISOString(),
                score: $this->score([$run->title, $run->executive_summary, $run->safety_summary, $run->recommendations], $context['q']),
                metadata: ['report_type' => $run->report_type]
            ));

        $sections = ClinicReportSection::query()
            ->with('run')
            ->whereHas('run', fn (Builder $query) => $this->applyReportScope($query, $context))
            ->when($context['date_from'], fn (Builder $query) => $query->where('created_at', '>=', $context['date_from']))
            ->when($context['date_to'], fn (Builder $query) => $query->where('created_at', '<=', $context['date_to']))
            ->where(function (Builder $query) use ($context) {
                $this->whereTextMatches($query, $context, [
                    'section_key',
                    'category',
                    'title',
                    'content',
                ], [
                    'metrics::text',
                ]);
            })
            ->latest()
            ->limit(12)
            ->get()
            ->map(function (ClinicReportSection $section) use ($context) {
                $run = $section->run;

                return $this->reportResult(
                    id: $section->id,
                    title: $section->title,
                    subtitle: $this->joinParts([$run?->title, $section->category]),
                    snippet: $this->snippet([$section->content], $context['q']),
                    createdAt: $section->created_at?->toISOString(),
                    score: $this->score([$section->title, $section->content], $context['q']),
                    metadata: ['section_key' => $section->section_key],
                    runId: $run?->id
                );
            });

        return $runs->merge($sections);
    }

    private function applyClinicalScope(
        Builder $query,
        array $context,
        string $doctorColumn = 'doctor_id',
        string $patientColumn = 'patient_id',
        ?string $visitColumn = 'patient_visit_id'
    ): Builder {
        return $query
            ->when($context['doctor_id'], fn (Builder $query) => $query->where($doctorColumn, $context['doctor_id']))
            ->when($context['patient_id'], fn (Builder $query) => $query->where($patientColumn, $context['patient_id']))
            ->when($context['visit_id'] && $visitColumn, fn (Builder $query) => $query->where($visitColumn, $context['visit_id']));
    }

    private function applyReportScope(Builder $query, array $context): Builder
    {
        if ($context['role'] === 'admin') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($context) {
            $query
                ->where('created_by_id', $context['user_id'])
                ->orWhere('scope_doctor_id', $context['user_id']);
        });
    }

    private function whereTextMatches(
        Builder $query,
        array $context,
        array $columns,
        array $rawExpressions = []
    ): void {
        foreach ($columns as $column) {
            $query->orWhere($column, 'ILIKE', $context['like']);
        }

        foreach ($rawExpressions as $expression) {
            $query->orWhereRaw("{$expression} ILIKE ?", [$context['like']]);
        }
    }

    private function visitResult(
        string $type,
        string $label,
        int $id,
        ?int $patientId,
        ?string $patientName,
        ?int $visitId,
        string $title,
        ?string $subtitle,
        ?string $snippet,
        ?string $createdAt,
        int $score,
        array $metadata = []
    ): array {
        return [
            'type' => $type,
            'label' => $label,
            'id' => $id,
            'patient_id' => $patientId,
            'patient_name' => $patientName,
            'visit_id' => $visitId,
            'title' => $title,
            'subtitle' => $subtitle,
            'snippet' => $snippet,
            'url' => $patientId && $visitId ? "/patients/{$patientId}/visits/{$visitId}" : null,
            'created_at' => $createdAt,
            'score' => $score,
            'metadata' => $metadata,
        ];
    }

    private function handoutResult(
        int $id,
        ?int $patientId,
        ?string $patientName,
        ?int $visitId,
        string $title,
        ?string $subtitle,
        ?string $snippet,
        ?string $createdAt,
        int $score,
        array $metadata = [],
        ?int $runId = null
    ): array {
        $targetId = $runId ?? $id;

        return [
            'type' => 'patient_handouts',
            'label' => 'Patient Handout',
            'id' => $id,
            'patient_id' => $patientId,
            'patient_name' => $patientName,
            'visit_id' => $visitId,
            'title' => $title,
            'subtitle' => $subtitle,
            'snippet' => $snippet,
            'url' => $patientId && $visitId && $targetId ? "/patients/{$patientId}/visits/{$visitId}/handouts/{$targetId}/print" : null,
            'created_at' => $createdAt,
            'score' => $score,
            'metadata' => $metadata,
        ];
    }

    private function reportResult(
        int $id,
        string $title,
        ?string $subtitle,
        ?string $snippet,
        ?string $createdAt,
        int $score,
        array $metadata = [],
        ?int $runId = null
    ): array {
        $targetId = $runId ?? $id;

        return [
            'type' => 'clinic_reports',
            'label' => 'Clinic Report',
            'id' => $id,
            'patient_id' => null,
            'patient_name' => null,
            'visit_id' => null,
            'title' => $title,
            'subtitle' => $subtitle,
            'snippet' => $snippet,
            'url' => $targetId ? "/clinic-reports/{$targetId}/print" : null,
            'created_at' => $createdAt,
            'score' => $score,
            'metadata' => $metadata,
        ];
    }

    private function score(array $values, string $q): int
    {
        $needle = Str::lower($q);
        $best = 0;

        foreach ($values as $value) {
            $text = Str::lower($this->stringify($value));

            if ($text === '') {
                continue;
            }

            if ($text === $needle) {
                $best = max($best, 100);
            } elseif (Str::startsWith($text, $needle)) {
                $best = max($best, 85);
            } elseif (Str::contains($text, $needle)) {
                $best = max($best, 65);
            }

            foreach (preg_split('/\s+/', $needle) ?: [] as $word) {
                if (strlen($word) >= 2 && Str::contains($text, $word)) {
                    $best += 4;
                }
            }
        }

        return min($best, 100);
    }

    private function snippet(array $values, string $q, int $limit = 240): ?string
    {
        $needle = Str::lower($q);

        foreach ($values as $value) {
            $text = trim($this->stringify($value));

            if ($text === '') {
                continue;
            }

            if (Str::contains(Str::lower($text), $needle)) {
                return Str::limit($this->aroundNeedle($text, $needle), $limit);
            }
        }

        foreach ($values as $value) {
            $text = trim($this->stringify($value));

            if ($text !== '') {
                return Str::limit($text, $limit);
            }
        }

        return null;
    }

    private function aroundNeedle(string $text, string $needle): string
    {
        $normalized = preg_replace('/\s+/', ' ', $text) ?: $text;
        $position = stripos($normalized, $needle);

        if ($position === false || $position <= 70) {
            return $normalized;
        }

        return '...'.substr($normalized, max(0, $position - 70));
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    private function joinParts(array $parts, string $separator = ' · '): ?string
    {
        $text = implode($separator, array_values(array_filter(
            array_map(fn ($part) => trim((string) $part), $parts),
            fn ($part) => $part !== ''
        )));

        return $text !== '' ? $text : null;
    }

    private function compareResults(array $a, array $b): int
    {
        $scoreComparison = ($b['score'] ?? 0) <=> ($a['score'] ?? 0);

        if ($scoreComparison !== 0) {
            return $scoreComparison;
        }

        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    }
}
