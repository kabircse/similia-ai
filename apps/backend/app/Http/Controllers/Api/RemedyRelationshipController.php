<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesDoctorOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateRemedyRelationshipRequest;
use App\Http\Resources\RemedyRelationshipRunResource;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\RemedyRelationshipRun;
use App\Services\Audit\AuditLogger;
use App\Services\RemedyRelationships\RemedyRelationshipService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use RuntimeException;

class RemedyRelationshipController extends Controller
{
    use ResolvesDoctorOwnership;

    public function index(Request $request, Patient $patient, PatientVisit $visit)
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $runs = RemedyRelationshipRun::query()
            ->with(['findings' => fn ($query) => $query->orderBy('rank')])
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return RemedyRelationshipRunResource::collection($runs);
    }

    public function show(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        RemedyRelationshipRun $remedyRelationshipRun
    ): RemedyRelationshipRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_unless($remedyRelationshipRun->patient_visit_id === $visit->id, 404);

        return new RemedyRelationshipRunResource(
            $remedyRelationshipRun->load(['findings' => fn ($query) => $query->orderBy('rank')])
        );
    }

    public function generate(
        GenerateRemedyRelationshipRequest $request,
        Patient $patient,
        PatientVisit $visit,
        RemedyRelationshipService $service,
        AuditLogger $auditLogger
    ): RemedyRelationshipRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $validated = $request->validated();

        try {
            $run = $service->generate(
                patient: $patient,
                visit: $visit,
                doctorId: $this->ownerDoctorIdForVisit($request, $visit),
                primaryRemedyId: $validated['primary_remedy_id'] ?? null,
                primaryRemedyCode: $validated['primary_remedy_code'] ?? null,
                primaryRemedyName: $validated['primary_remedy_name'] ?? null,
                comparisonRemedyId: $validated['comparison_remedy_id'] ?? null,
                comparisonRemedyCode: $validated['comparison_remedy_code'] ?? null,
                comparisonRemedyName: $validated['comparison_remedy_name'] ?? null,
                purpose: $validated['purpose'] ?? 'general',
                prescriptionId: $validated['prescription_id'] ?? null,
                includeVisitContext: $request->boolean('include_visit_context', true),
                includeFollowUpContext: $request->boolean('include_follow_up_context', true),
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
            category: 'relationship',
            action: 'generated_remedy_relationship',
            title: 'Remedy relationship guidance generated',
            description: $run->relationship_summary,
            patient: $patient,
            visit: $visit,
            entity: $run,
            metadata: [
                'primary_remedy_name' => $run->primary_remedy_name,
                'comparison_remedy_name' => $run->comparison_remedy_name,
                'purpose' => $run->purpose,
                'findings_count' => $run->findings()->count(),
            ]
        );

        return new RemedyRelationshipRunResource(
            $run->load(['findings' => fn ($query) => $query->orderBy('rank')])
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
