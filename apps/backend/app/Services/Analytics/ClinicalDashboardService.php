<?php

namespace App\Services\Analytics;

use App\Models\FollowUpAnalysisRun;
use App\Models\Patient;
use App\Models\PatientFee;
use App\Models\PatientHandoutRun;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\PrescriptionReviewRun;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClinicalDashboardService
{
    public function build(array $filters, int $userId, string $role): array
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);
        $doctorId = $this->resolveDoctorId($filters, $userId, $role);

        return [
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'doctor_id' => $doctorId,
                'period' => $filters['period'] ?? '30d',
            ],

            'kpis' => $this->kpis($dateFrom, $dateTo, $doctorId),

            'clinic_activity' => [
                'visits_by_day' => $this->visitsByDay($dateFrom, $dateTo, $doctorId),
                'new_patients_by_day' => $this->newPatientsByDay($dateFrom, $dateTo, $doctorId),
                'visit_type_distribution' => $this->visitTypeDistribution($dateFrom, $dateTo, $doctorId),
            ],

            'outcomes' => [
                'response_level_distribution' => $this->responseLevelDistribution($dateFrom, $dateTo, $doctorId),
                'progress_score_trend' => $this->progressScoreTrend($dateFrom, $dateTo, $doctorId),
                'latest_outcome_cases' => $this->latestOutcomeCases($dateFrom, $dateTo, $doctorId),
            ],

            'remedies' => [
                'top_prescribed_remedies' => $this->topPrescribedRemedies($dateFrom, $dateTo, $doctorId),
                'top_potencies' => $this->topPotencies($dateFrom, $dateTo, $doctorId),
            ],

            'safety' => [
                'prescription_review_status' => $this->prescriptionReviewStatus($dateFrom, $dateTo, $doctorId),
                'red_flag_count' => $this->redFlagCount($dateFrom, $dateTo, $doctorId),
                'recent_red_flags' => $this->recentRedFlags($dateFrom, $dateTo, $doctorId),
            ],

            'finance' => $this->finance($dateFrom, $dateTo, $doctorId),

            'follow_ups' => [
                'due_today' => $this->followUpsDue(days: 0, doctorId: $doctorId),
                'due_next_7_days' => $this->followUpsDue(days: 7, doctorId: $doctorId),
                'overdue' => $this->overdueFollowUps($doctorId),
            ],

            'recent_alerts' => $this->recentAlerts($dateFrom, $dateTo, $doctorId),
        ];
    }

    private function resolveDateRange(array $filters): array
    {
        $period = $filters['period'] ?? '30d';

        if ($period === 'custom' && ! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            return [
                Carbon::parse($filters['date_from'])->startOfDay(),
                Carbon::parse($filters['date_to'])->endOfDay(),
            ];
        }

        return match ($period) {
            '7d' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            '90d' => [now()->subDays(89)->startOfDay(), now()->endOfDay()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
        };
    }

    private function resolveDoctorId(array $filters, int $userId, string $role): ?int
    {
        if ($role === 'admin') {
            return $filters['doctor_id'] ?? null;
        }

        return $userId;
    }

    private function kpis(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): array
    {
        $patients = Patient::query()
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        $visits = PatientVisit::query()
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('visit_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->count();

        $followUps = PatientVisit::query()
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->where('visit_type', 'follow_up')
            ->whereBetween('visit_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->count();

        $prescriptions = PatientPrescription::query()
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        $outcomeAnalyses = FollowUpAnalysisRun::query()
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        $avgProgressScore = FollowUpAnalysisRun::query()
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->avg('progress_score');

        $handouts = PatientHandoutRun::query()
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        return [
            'new_patients' => $patients,
            'visits' => $visits,
            'follow_up_visits' => $followUps,
            'prescriptions' => $prescriptions,
            'outcome_analyses' => $outcomeAnalyses,
            'average_progress_score' => round((float) $avgProgressScore, 2),
            'patient_handouts' => $handouts,
        ];
    }

    private function visitsByDay(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): array
    {
        $rows = PatientVisit::query()
            ->selectRaw('DATE(visit_date) as date, COUNT(*) as total')
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('visit_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->groupByRaw('DATE(visit_date)')
            ->orderByRaw('DATE(visit_date)')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        return $this->dateSeries($dateFrom, $dateTo, fn (string $date) => [
            'date' => $date,
            'total' => (int) ($rows[$date]->total ?? 0),
        ]);
    }

    private function newPatientsByDay(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): array
    {
        $rows = Patient::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        return $this->dateSeries($dateFrom, $dateTo, fn (string $date) => [
            'date' => $date,
            'total' => (int) ($rows[$date]->total ?? 0),
        ]);
    }

    private function visitTypeDistribution(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): array
    {
        return PatientVisit::query()
            ->selectRaw('visit_type, COUNT(*) as total')
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('visit_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->groupBy('visit_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'visit_type' => $row->visit_type ?? 'unknown',
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    private function responseLevelDistribution(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): array
    {
        return FollowUpAnalysisRun::query()
            ->selectRaw('response_level, COUNT(*) as total')
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('response_level')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'response_level' => $row->response_level ?? 'unclear',
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    private function progressScoreTrend(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): array
    {
        $rows = FollowUpAnalysisRun::query()
            ->selectRaw('DATE(created_at) as date, AVG(progress_score) as average_score, COUNT(*) as total')
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        return $this->dateSeries($dateFrom, $dateTo, fn (string $date) => [
            'date' => $date,
            'average_score' => round((float) ($rows[$date]->average_score ?? 0), 2),
            'total' => (int) ($rows[$date]->total ?? 0),
        ]);
    }

    private function latestOutcomeCases(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): array
    {
        return FollowUpAnalysisRun::query()
            ->with(['patient:id,name,phone', 'visit:id,visit_date,chief_complaint'])
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (FollowUpAnalysisRun $run) => [
                'id' => $run->id,
                'patient_id' => $run->patient_id,
                'patient_name' => $run->patient?->name,
                'visit_id' => $run->patient_visit_id,
                'visit_date' => $run->visit?->visit_date?->toDateString(),
                'chief_complaint' => $run->visit?->chief_complaint,
                'response_level' => $run->response_level,
                'progress_score' => (float) $run->progress_score,
                'summary' => $run->analysis_summary,
                'red_flags' => $run->red_flags ?? [],
            ])
            ->values()
            ->all();
    }

    private function topPrescribedRemedies(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): array
    {
        return PatientPrescription::query()
            ->selectRaw("COALESCE(remedy_name, remedy_code, 'Unknown') as remedy, COUNT(*) as total")
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupByRaw("COALESCE(remedy_name, remedy_code, 'Unknown')")
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'remedy' => $row->remedy,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    private function topPotencies(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): array
    {
        return PatientPrescription::query()
            ->selectRaw("COALESCE(potency, 'Unknown') as potency, COUNT(*) as total")
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupByRaw("COALESCE(potency, 'Unknown')")
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'potency' => $row->potency,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    private function prescriptionReviewStatus(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): array
    {
        return PrescriptionReviewRun::query()
            ->selectRaw('review_status, COUNT(*) as total')
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('review_status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'review_status' => $row->review_status ?? 'unknown',
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    private function redFlagCount(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): int
    {
        return $this->followUpRunsInRange($dateFrom, $dateTo, $doctorId)
            ->filter(fn (FollowUpAnalysisRun $run) => $this->hasListItems($run->red_flags))
            ->count();
    }

    private function recentRedFlags(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): array
    {
        return $this->followUpRunsInRange($dateFrom, $dateTo, $doctorId)
            ->filter(fn (FollowUpAnalysisRun $run) => $this->hasListItems($run->red_flags))
            ->take(10)
            ->map(fn (FollowUpAnalysisRun $run) => [
                'id' => $run->id,
                'patient_id' => $run->patient_id,
                'patient_name' => $run->patient?->name,
                'patient_phone' => $run->patient?->phone,
                'visit_id' => $run->patient_visit_id,
                'visit_date' => $run->visit?->visit_date?->toDateString(),
                'chief_complaint' => $run->visit?->chief_complaint,
                'red_flags' => array_values($run->red_flags ?? []),
                'summary' => $run->analysis_summary,
            ])
            ->values()
            ->all();
    }

    private function finance(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): array
    {
        $query = PatientFee::query()
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        return [
            'total_amount' => (float) (clone $query)->sum('total_amount'),
            'paid_amount' => (float) (clone $query)->sum('paid_amount'),
            'due_amount' => (float) (clone $query)->sum('due_amount'),
            'unpaid_count' => (clone $query)->where('payment_status', 'unpaid')->count(),
            'partial_count' => (clone $query)->where('payment_status', 'partial')->count(),
            'paid_count' => (clone $query)->where('payment_status', 'paid')->count(),
        ];
    }

    private function followUpsDue(int $days, ?int $doctorId): array
    {
        $dateFrom = now()->toDateString();
        $dateTo = now()->addDays($days)->toDateString();

        return PatientPrescription::query()
            ->with(['patient:id,name,phone', 'visit:id,visit_date,chief_complaint'])
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereNotNull('follow_up_date')
            ->whereBetween('follow_up_date', [$dateFrom, $dateTo])
            ->orderBy('follow_up_date')
            ->limit(20)
            ->get()
            ->map(fn (PatientPrescription $prescription) => $this->followUpItem($prescription))
            ->values()
            ->all();
    }

    private function overdueFollowUps(?int $doctorId): array
    {
        return PatientPrescription::query()
            ->with(['patient:id,name,phone', 'visit:id,visit_date,chief_complaint'])
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<', now()->toDateString())
            ->orderBy('follow_up_date')
            ->limit(20)
            ->get()
            ->map(fn (PatientPrescription $prescription) => $this->followUpItem($prescription))
            ->values()
            ->all();
    }

    private function recentAlerts(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): array
    {
        $redFlagAlerts = collect($this->recentRedFlags($dateFrom, $dateTo, $doctorId))
            ->map(fn (array $item) => [
                'type' => 'red_flag',
                'severity' => 'critical',
                'patient_id' => $item['patient_id'],
                'patient_name' => $item['patient_name'],
                'visit_id' => $item['visit_id'],
                'title' => 'Follow-up red flag',
                'description' => implode('; ', $item['red_flags']),
                'created_at' => null,
            ]);

        $reviewAlerts = PrescriptionReviewRun::query()
            ->with(['patient:id,name'])
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereIn('review_status', ['blocked', 'safety_warning', 'incomplete'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (PrescriptionReviewRun $run) => [
                'type' => 'prescription_review',
                'severity' => $run->review_status === 'blocked' ? 'critical' : 'warning',
                'patient_id' => $run->patient_id,
                'patient_name' => $run->patient?->name,
                'visit_id' => $run->patient_visit_id,
                'title' => 'Prescription review '.$run->review_status,
                'description' => $run->risk_summary ?: $run->review_summary,
                'created_at' => $run->created_at?->toISOString(),
            ]);

        return $redFlagAlerts
            ->merge($reviewAlerts)
            ->take(12)
            ->values()
            ->all();
    }

    private function followUpRunsInRange(Carbon $dateFrom, Carbon $dateTo, ?int $doctorId): Collection
    {
        return FollowUpAnalysisRun::query()
            ->with(['patient:id,name,phone', 'visit:id,visit_date,chief_complaint'])
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->latest()
            ->get();
    }

    private function followUpItem(PatientPrescription $prescription): array
    {
        return [
            'prescription_id' => $prescription->id,
            'patient_id' => $prescription->patient_id,
            'patient_name' => $prescription->patient?->name,
            'patient_phone' => $prescription->patient?->phone,
            'visit_id' => $prescription->patient_visit_id,
            'chief_complaint' => $prescription->visit?->chief_complaint,
            'follow_up_date' => $prescription->follow_up_date?->toDateString(),
            'remedy_name' => $prescription->remedy_name,
            'potency' => $prescription->potency,
        ];
    }

    private function dateSeries(Carbon $dateFrom, Carbon $dateTo, callable $map): array
    {
        return collect(CarbonPeriod::create($dateFrom->copy()->startOfDay(), $dateTo->copy()->startOfDay()))
            ->map(fn (Carbon $date) => $map($date->toDateString()))
            ->values()
            ->all();
    }

    private function hasListItems(mixed $items): bool
    {
        if (! is_array($items)) {
            return false;
        }

        return collect($items)->contains(fn ($item) => trim((string) $item) !== '');
    }
}
