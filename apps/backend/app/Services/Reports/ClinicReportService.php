<?php

namespace App\Services\Reports;

use App\Models\ClinicReportRun;
use App\Models\ClinicReportSection;
use App\Services\Analytics\ClinicalDashboardService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClinicReportService
{
    public function __construct(
        private readonly ClinicalDashboardService $dashboardService
    ) {}

    public function generate(array $input, int $userId, string $role): ClinicReportRun
    {
        [$periodStart, $periodEnd, $dashboardPeriod] = $this->resolveReportPeriod($input);

        $doctorId = $role === 'admin'
            ? ($input['doctor_id'] ?? null)
            : $userId;

        $dashboardSnapshot = $this->dashboardService->build(
            filters: [
                'period' => $dashboardPeriod,
                'date_from' => $periodStart->toDateString(),
                'date_to' => $periodEnd->toDateString(),
                'doctor_id' => $doctorId,
            ],
            userId: $userId,
            role: $role
        );

        $payload = [
            'report_type' => $input['report_type'] ?? 'monthly',
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'dashboard_snapshot' => $dashboardSnapshot,
            'response_language' => $input['response_language'] ?? 'auto',
            'include_finance' => $input['include_finance'] ?? true,
            'include_safety' => $input['include_safety'] ?? true,
            'include_follow_ups' => $input['include_follow_ups'] ?? true,
            'include_recommendations' => $input['include_recommendations'] ?? true,
        ];

        $response = Http::timeout((int) config('services.ai_service.timeout', 30))
            ->acceptJson()
            ->post(rtrim((string) config('services.ai_service.url'), '/').'/clinic-report/monthly-summary', $payload);

        if ($response->failed()) {
            throw new RuntimeException('AI service failed with status '.$response->status().'.');
        }

        $summary = $response->json('data') ?? $response->json();

        if (! is_array($summary)) {
            throw new RuntimeException('AI service returned an invalid clinic report response.');
        }

        return DB::transaction(function () use (
            $input,
            $userId,
            $doctorId,
            $periodStart,
            $periodEnd,
            $dashboardSnapshot,
            $summary
        ): ClinicReportRun {
            $run = ClinicReportRun::create([
                'created_by_id' => $userId,
                'scope_doctor_id' => $doctorId,

                'report_type' => $input['report_type'] ?? 'monthly',
                'status' => 'completed',

                'response_language' => $input['response_language'] ?? 'auto',
                'resolved_language' => $summary['resolved_language'] ?? null,

                'period_start' => $periodStart,
                'period_end' => $periodEnd,

                'title' => $summary['title'] ?? null,

                'executive_summary' => $summary['executive_summary'] ?? null,
                'clinical_activity_summary' => $summary['clinical_activity_summary'] ?? null,
                'outcome_summary' => $summary['outcome_summary'] ?? null,
                'remedy_summary' => $summary['remedy_summary'] ?? null,
                'safety_summary' => $summary['safety_summary'] ?? null,
                'finance_summary' => $summary['finance_summary'] ?? null,
                'follow_up_summary' => $summary['follow_up_summary'] ?? null,

                'key_metrics' => $summary['key_metrics'] ?? [],
                'dashboard_snapshot' => $dashboardSnapshot,
                'recommendations' => $summary['recommendations'] ?? [],
                'limitations' => $summary['limitations'] ?? [],

                'safety_note' => $summary['safety_note'] ?? null,

                'metadata' => [
                    'include_finance' => $input['include_finance'] ?? true,
                    'include_safety' => $input['include_safety'] ?? true,
                    'include_follow_ups' => $input['include_follow_ups'] ?? true,
                    'include_recommendations' => $input['include_recommendations'] ?? true,
                ],
            ]);

            foreach (($summary['sections'] ?? []) as $section) {
                ClinicReportSection::create([
                    'clinic_report_run_id' => $run->id,
                    'section_key' => $section['section_key'] ?? 'overview',
                    'category' => $section['category'] ?? 'summary',
                    'sort_order' => $section['sort_order'] ?? 1,
                    'title' => $section['title'] ?? 'Report Section',
                    'content' => $section['content'] ?? '',
                    'metrics' => $section['metrics'] ?? [],
                    'metadata' => $section['metadata'] ?? [],
                ]);
            }

            return $run->load(['sections' => fn ($query) => $query->orderBy('sort_order')]);
        });
    }

    public function markExported(ClinicReportRun $run): ClinicReportRun
    {
        $run->update([
            'exported_at' => now(),
        ]);

        return $run->fresh()->load(['sections' => fn ($query) => $query->orderBy('sort_order')]);
    }

    public function markPrinted(ClinicReportRun $run): ClinicReportRun
    {
        $run->update([
            'printed_at' => now(),
        ]);

        return $run->fresh()->load(['sections' => fn ($query) => $query->orderBy('sort_order')]);
    }

    public function csvRows(ClinicReportRun $run): array
    {
        $rows = [
            ['Report Title', $run->title],
            ['Period Start', $run->period_start?->toDateString()],
            ['Period End', $run->period_end?->toDateString()],
            ['Report Type', $run->report_type],
            ['Generated At', $run->created_at?->toDateTimeString()],
            [],
            ['Metric', 'Value'],
        ];

        foreach (($run->key_metrics ?? []) as $key => $value) {
            $rows[] = [$key, is_scalar($value) || $value === null ? $value : json_encode($value)];
        }

        $rows[] = [];
        $rows[] = ['Section', 'Content'];

        foreach ($run->sections()->orderBy('sort_order')->get() as $section) {
            $rows[] = [$section->title, $section->content];
        }

        $rows[] = [];
        $rows[] = ['Safety Note', $run->safety_note];

        return $rows;
    }

    private function resolveReportPeriod(array $input): array
    {
        $period = $input['period'] ?? 'last_month';

        if ($period === 'custom') {
            $start = Carbon::parse($input['date_from'] ?? now()->startOfMonth())->startOfDay();
            $end = Carbon::parse($input['date_to'] ?? now()->endOfMonth())->endOfDay();

            if ($end->lt($start)) {
                throw new RuntimeException('Report end date must be on or after the start date.');
            }

            return [$start, $end, 'custom'];
        }

        if ($period === 'this_month') {
            return [now()->startOfMonth(), now()->endOfMonth(), 'custom'];
        }

        if ($period === 'this_year') {
            return [now()->startOfYear(), now()->endOfYear(), 'custom'];
        }

        $lastMonth = now()->subMonthNoOverflow();

        return [
            $lastMonth->copy()->startOfMonth(),
            $lastMonth->copy()->endOfMonth(),
            'custom',
        ];
    }
}
