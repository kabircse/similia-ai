<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesDoctorOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneratePrescriptionReviewRequest;
use App\Http\Requests\UpdatePrescriptionReviewCheckRequest;
use App\Http\Resources\PrescriptionReviewRunResource;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\PrescriptionReviewCheck;
use App\Models\PrescriptionReviewRun;
use App\Services\Audit\AuditLogger;
use App\Services\PrescriptionReviews\PrescriptionReviewService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use RuntimeException;

class PrescriptionReviewController extends Controller
{
    use ResolvesDoctorOwnership;

    public function index(Request $request, Patient $patient, PatientVisit $visit)
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $runs = PrescriptionReviewRun::query()
            ->with(['checks' => fn ($query) => $query->orderBy('id')])
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return PrescriptionReviewRunResource::collection($runs);
    }

    public function show(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        PrescriptionReviewRun $prescriptionReviewRun
    ): PrescriptionReviewRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_unless($prescriptionReviewRun->patient_visit_id === $visit->id, 404);

        return new PrescriptionReviewRunResource(
            $prescriptionReviewRun->load(['checks' => fn ($query) => $query->orderBy('id')])
        );
    }

    public function generate(
        GeneratePrescriptionReviewRequest $request,
        Patient $patient,
        PatientVisit $visit,
        PrescriptionReviewService $service,
        AuditLogger $auditLogger
    ): PrescriptionReviewRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $validated = $request->validated();

        try {
            $run = $service->generate(
                patient: $patient,
                visit: $visit,
                doctorId: $this->ownerDoctorIdForVisit($request, $visit),
                prescriptionId: $validated['prescription_id'] ?? null,
                includeRemedySuggestion: $request->boolean('include_remedy_suggestion', true),
                includePotencyGuidance: $request->boolean('include_potency_guidance', true),
                includeRelationshipGuidance: $request->boolean('include_relationship_guidance', true),
                includeFollowUpAnalysis: $request->boolean('include_follow_up_analysis', true),
                responseLanguage: $validated['response_language'] ?? 'auto'
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
            action: 'generated_prescription_review',
            title: 'Prescription decision review generated',
            description: $run->review_summary,
            patient: $patient,
            visit: $visit,
            entity: $run,
            metadata: [
                'prescription_id' => $run->prescription_id,
                'review_status' => $run->review_status,
                'safety_score' => $run->safety_score,
                'checks_count' => $run->checks()->count(),
            ]
        );

        return new PrescriptionReviewRunResource(
            $run->load(['checks' => fn ($query) => $query->orderBy('id')])
        );
    }

    public function updateCheck(
        UpdatePrescriptionReviewCheckRequest $request,
        Patient $patient,
        PatientVisit $visit,
        PrescriptionReviewRun $prescriptionReviewRun,
        PrescriptionReviewCheck $prescriptionReviewCheck,
        PrescriptionReviewService $service,
        AuditLogger $auditLogger
    ): PrescriptionReviewRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_unless($prescriptionReviewRun->patient_visit_id === $visit->id, 404);
        abort_unless($prescriptionReviewCheck->prescription_review_run_id === $prescriptionReviewRun->id, 404);

        $validated = $request->validated();

        $run = $service->updateCheck(
            run: $prescriptionReviewRun,
            check: $prescriptionReviewCheck,
            doctorId: $this->ownerDoctorIdForVisit($request, $visit),
            status: $validated['status'],
            doctorNote: $validated['doctor_note'] ?? null
        );

        $auditLogger->log(
            request: $request,
            category: 'prescription',
            action: 'confirmed_prescription_review_check',
            title: 'Prescription review check updated',
            description: $prescriptionReviewCheck->title,
            patient: $patient,
            visit: $visit,
            entity: $prescriptionReviewCheck,
            metadata: [
                'review_run_id' => $run->id,
                'check_key' => $prescriptionReviewCheck->check_key,
                'status' => $validated['status'],
                'review_status' => $run->review_status,
            ]
        );

        return new PrescriptionReviewRunResource($run);
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
