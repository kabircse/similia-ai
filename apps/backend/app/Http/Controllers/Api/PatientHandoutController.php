<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesDoctorOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneratePatientHandoutRequest;
use App\Http\Resources\PatientHandoutRunResource;
use App\Models\Patient;
use App\Models\PatientHandoutRun;
use App\Models\PatientVisit;
use App\Services\Audit\AuditLogger;
use App\Services\PatientHandouts\PatientHandoutService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use RuntimeException;

class PatientHandoutController extends Controller
{
    use ResolvesDoctorOwnership;

    public function index(Request $request, Patient $patient, PatientVisit $visit)
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $runs = PatientHandoutRun::query()
            ->with(['sections' => fn ($query) => $query->orderBy('sort_order')])
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return PatientHandoutRunResource::collection($runs);
    }

    public function show(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        PatientHandoutRun $patientHandout
    ): PatientHandoutRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_unless($patientHandout->patient_visit_id === $visit->id, 404);

        return new PatientHandoutRunResource(
            $patientHandout->load(['sections' => fn ($query) => $query->orderBy('sort_order')])
        );
    }

    public function generate(
        GeneratePatientHandoutRequest $request,
        Patient $patient,
        PatientVisit $visit,
        PatientHandoutService $service,
        AuditLogger $auditLogger
    ): PatientHandoutRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $validated = $request->validated();

        try {
            $run = $service->generate(
                patient: $patient,
                visit: $visit,
                doctorId: $this->ownerDoctorIdForVisit($request, $visit),
                prescriptionId: $validated['prescription_id'] ?? null,
                prescriptionReviewRunId: $validated['prescription_review_run_id'] ?? null,
                handoutType: $validated['handout_type'] ?? 'prescription',
                responseLanguage: $validated['response_language'] ?? 'auto',
                style: $validated['style'] ?? 'simple',
                includeClinicBranding: $request->boolean('include_clinic_branding', true),
                includeWarningSigns: $request->boolean('include_warning_signs', true),
                includeDoAndDont: $request->boolean('include_do_and_dont', true),
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
            category: 'prescription',
            action: 'generated_patient_handout',
            title: 'Patient instruction handout generated',
            description: $run->title,
            patient: $patient,
            visit: $visit,
            entity: $run,
            metadata: [
                'prescription_id' => $run->prescription_id,
                'prescription_review_run_id' => $run->prescription_review_run_id,
                'handout_type' => $run->handout_type,
                'resolved_language' => $run->resolved_language,
                'sections_count' => $run->sections()->count(),
            ]
        );

        return new PatientHandoutRunResource(
            $run->load(['sections' => fn ($query) => $query->orderBy('sort_order')])
        );
    }

    public function markPrinted(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        PatientHandoutRun $patientHandout,
        PatientHandoutService $service,
        AuditLogger $auditLogger
    ): PatientHandoutRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_unless($patientHandout->patient_visit_id === $visit->id, 404);

        $run = $service->markPrinted($patientHandout);

        $auditLogger->log(
            request: $request,
            category: 'print',
            action: 'printed_patient_handout',
            title: 'Patient handout printed',
            description: $run->title,
            patient: $patient,
            visit: $visit,
            entity: $run,
            metadata: [
                'prescription_id' => $run->prescription_id,
                'handout_type' => $run->handout_type,
                'resolved_language' => $run->resolved_language,
            ]
        );

        return new PatientHandoutRunResource($run);
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
