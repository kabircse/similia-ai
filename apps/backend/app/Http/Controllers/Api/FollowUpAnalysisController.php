<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesDoctorOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateFollowUpAnalysisRequest;
use App\Http\Resources\FollowUpAnalysisRunResource;
use App\Models\FollowUpAnalysisRun;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Services\Audit\AuditLogger;
use App\Services\FollowUps\FollowUpAnalysisService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use RuntimeException;

class FollowUpAnalysisController extends Controller
{
    use ResolvesDoctorOwnership;

    public function index(Request $request, Patient $patient, PatientVisit $visit)
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $runs = FollowUpAnalysisRun::query()
            ->with(['progressItems' => fn ($query) => $query->orderBy('id')])
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return FollowUpAnalysisRunResource::collection($runs);
    }

    public function show(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        FollowUpAnalysisRun $followUpAnalysisRun
    ): FollowUpAnalysisRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_unless($followUpAnalysisRun->patient_visit_id === $visit->id, 404);

        return new FollowUpAnalysisRunResource(
            $followUpAnalysisRun->load(['progressItems' => fn ($query) => $query->orderBy('id')])
        );
    }

    public function generate(
        GenerateFollowUpAnalysisRequest $request,
        Patient $patient,
        PatientVisit $visit,
        FollowUpAnalysisService $service,
        AuditLogger $auditLogger
    ): FollowUpAnalysisRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $validated = $request->validated();

        try {
            $run = $service->analyze(
                patient: $patient,
                currentVisit: $visit,
                doctorId: $this->ownerDoctorIdForVisit($request, $visit),
                previousVisitId: $validated['previous_visit_id'] ?? null,
                prescriptionId: $validated['prescription_id'] ?? null,
                includeTimelineContext: $request->boolean('include_timeline_context', true),
                limitPreviousVisits: (int) ($validated['limit_previous_visits'] ?? 3),
                responseLanguage: $validated['response_language'] ?? 'auto'
            );
        } catch (ConnectionException) {
            abort(502, 'AI service is not reachable. Please make sure FastAPI is running on port 8001.');
        } catch (RuntimeException $exception) {
            $status = str_starts_with($exception->getMessage(), 'No previous visit') ? 422 : 502;
            abort($status, $exception->getMessage());
        }

        $auditLogger->log(
            request: $request,
            category: 'follow_up',
            action: 'generated_follow_up_analysis',
            title: 'AI follow-up analysis generated',
            description: $run->analysis_summary,
            patient: $patient,
            visit: $visit,
            entity: $run,
            metadata: [
                'response_level' => $run->response_level,
                'progress_score' => $run->progress_score,
                'previous_visit_id' => $run->previous_visit_id,
                'prescription_id' => $run->prescription_id,
            ]
        );

        return new FollowUpAnalysisRunResource(
            $run->load(['progressItems' => fn ($query) => $query->orderBy('id')])
        );
    }

    private function ensureCanAccessVisit(Request $request, Patient $patient, PatientVisit $visit): void
    {
        $user = $request->user();

        abort_unless($visit->patient_id === $patient->id, 404);

        if ($user->role === 'admin') {
            return;
        }

        abort_unless($patient->doctor_id === $user->id, 403);
        abort_unless($visit->doctor_id === $user->id, 403);
    }
}
