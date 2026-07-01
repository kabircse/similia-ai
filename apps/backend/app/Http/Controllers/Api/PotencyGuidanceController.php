<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneratePotencyGuidanceRequest;
use App\Http\Resources\PotencyGuidanceRunResource;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\PotencyGuidanceRun;
use App\Services\Audit\AuditLogger;
use App\Services\Potency\PotencyGuidanceService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use RuntimeException;

class PotencyGuidanceController extends Controller
{
    public function index(Request $request, Patient $patient, PatientVisit $visit)
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $runs = PotencyGuidanceRun::query()
            ->with(['options' => fn ($query) => $query->orderBy('rank')])
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return PotencyGuidanceRunResource::collection($runs);
    }

    public function show(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        PotencyGuidanceRun $potencyGuidanceRun
    ): PotencyGuidanceRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_unless($potencyGuidanceRun->patient_visit_id === $visit->id, 404);

        return new PotencyGuidanceRunResource(
            $potencyGuidanceRun->load(['options' => fn ($query) => $query->orderBy('rank')])
        );
    }

    public function generate(
        GeneratePotencyGuidanceRequest $request,
        Patient $patient,
        PatientVisit $visit,
        PotencyGuidanceService $service,
        AuditLogger $auditLogger
    ): PotencyGuidanceRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $validated = $request->validated();
        $settings = [
            'case_phase' => $validated['case_phase'] ?? 'unclear',
            'patient_sensitivity' => $validated['patient_sensitivity'] ?? 'unclear',
            'vitality_level' => $validated['vitality_level'] ?? 'unclear',
            'pathology_depth' => $validated['pathology_depth'] ?? 'unclear',
            'include_organon' => $request->boolean('include_organon', true),
            'include_philosophy' => $request->boolean('include_philosophy', true),
            'include_follow_up_context' => $request->boolean('include_follow_up_context', true),
            'response_language' => $validated['response_language'] ?? 'auto',
        ];

        try {
            $run = $service->generate(
                patient: $patient,
                visit: $visit,
                doctorId: $request->user()->id,
                prescriptionId: $validated['prescription_id'] ?? null,
                remedyId: $validated['remedy_id'] ?? null,
                remedyName: $validated['remedy_name'] ?? null,
                remedyCode: $validated['remedy_code'] ?? null,
                settings: $settings
            );
        } catch (ConnectionException) {
            abort(502, 'AI service is not reachable. Please make sure FastAPI is running on port 8001.');
        } catch (RuntimeException $exception) {
            abort(502, $exception->getMessage());
        }

        $auditLogger->log(
            request: $request,
            category: 'potency',
            action: 'generated_potency_guidance',
            title: 'Potency guidance generated',
            description: $run->guidance_summary,
            patient: $patient,
            visit: $visit,
            entity: $run,
            metadata: [
                'remedy_name' => $run->remedy_name,
                'case_phase' => $run->case_phase,
                'vitality_level' => $run->vitality_level,
                'sensitivity_level' => $run->sensitivity_level,
                'pathology_depth' => $run->pathology_depth,
                'options_count' => $run->options()->count(),
            ]
        );

        return new PotencyGuidanceRunResource(
            $run->load(['options' => fn ($query) => $query->orderBy('rank')])
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
