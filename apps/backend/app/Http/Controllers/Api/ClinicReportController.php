<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateClinicReportRequest;
use App\Http\Resources\ClinicReportRunResource;
use App\Models\ClinicReportRun;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\ClinicReportService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClinicReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $reports = ClinicReportRun::query()
            ->with(['sections' => fn ($query) => $query->orderBy('sort_order')])
            ->when($user->role !== 'admin', fn ($query) => $query->where('created_by_id', $user->id))
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return ClinicReportRunResource::collection($reports);
    }

    public function show(Request $request, ClinicReportRun $clinicReport): ClinicReportRunResource
    {
        $this->ensureCanAccessReport($request, $clinicReport);

        return new ClinicReportRunResource(
            $clinicReport->load(['sections' => fn ($query) => $query->orderBy('sort_order')])
        );
    }

    public function generate(
        GenerateClinicReportRequest $request,
        ClinicReportService $service,
        AuditLogger $auditLogger
    ): JsonResponse {
        $user = $request->user();

        try {
            $report = $service->generate(
                input: $request->validated(),
                userId: $user->id,
                role: $user->role
            );
        } catch (ConnectionException) {
            abort(502, 'AI service is not reachable. Please make sure FastAPI is running on port 8001.');
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            $status = str_starts_with($message, 'AI service') ? 502 : 422;

            abort($status, $message);
        }

        $auditLogger->log(
            request: $request,
            category: 'clinic_report',
            action: 'generated_clinic_report',
            title: 'Clinic report generated',
            description: $report->title,
            entity: $report,
            metadata: [
                'report_type' => $report->report_type,
                'period_start' => $report->period_start?->toDateString(),
                'period_end' => $report->period_end?->toDateString(),
                'scope_doctor_id' => $report->scope_doctor_id,
                'resolved_language' => $report->resolved_language,
            ]
        );

        return (new ClinicReportRunResource($report))
            ->response()
            ->setStatusCode(200);
    }

    public function exportCsv(
        Request $request,
        ClinicReportRun $clinicReport,
        ClinicReportService $service,
        AuditLogger $auditLogger
    ): StreamedResponse {
        $this->ensureCanAccessReport($request, $clinicReport);

        $report = $service->markExported($clinicReport);
        $rows = $service->csvRows($report);

        $auditLogger->log(
            request: $request,
            category: 'clinic_report',
            action: 'exported_clinic_report_csv',
            title: 'Clinic report exported as CSV',
            description: $report->title,
            entity: $report
        );

        $periodStart = $report->period_start?->format('Y-m-d') ?? 'start';
        $periodEnd = $report->period_end?->format('Y-m-d') ?? 'end';
        $filename = "clinic-report-{$periodStart}-to-{$periodEnd}.csv";

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function markPrinted(
        Request $request,
        ClinicReportRun $clinicReport,
        ClinicReportService $service,
        AuditLogger $auditLogger
    ): ClinicReportRunResource {
        $this->ensureCanAccessReport($request, $clinicReport);

        $report = $service->markPrinted($clinicReport);

        $auditLogger->log(
            request: $request,
            category: 'clinic_report',
            action: 'printed_clinic_report',
            title: 'Clinic report marked as printed',
            description: $report->title,
            entity: $report
        );

        return new ClinicReportRunResource($report);
    }

    private function ensureCanAccessReport(Request $request, ClinicReportRun $report): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        abort_unless($report->created_by_id === $user->id, 403);
    }
}
