<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesDoctorOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientVisitRequest;
use App\Http\Requests\UpdatePatientVisitRequest;
use App\Http\Resources\PatientVisitResource;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientVisitController extends Controller
{
    use ResolvesDoctorOwnership;

    public function index(Request $request, Patient $patient)
    {
        $this->ensureCanAccessPatient($request, $patient);

        $visits = PatientVisit::query()
            ->where('patient_id', $patient->id)
            ->latest('visit_date')
            ->paginate($request->integer('per_page', 10));

        return PatientVisitResource::collection($visits);
    }

    public function store(
        StorePatientVisitRequest $request,
        Patient $patient,
        AuditLogger $auditLogger
    ): PatientVisitResource
    {
        $this->ensureCanAccessPatient($request, $patient);

        $visit = PatientVisit::create([
            ...$request->validated(),
            'patient_id' => $patient->id,
            'doctor_id' => $this->ownerDoctorIdForPatient($request, $patient),
            'case_sections' => $request->validated('case_sections') ?? [],
            'missing_questions' => [],
            'red_flags' => [],
        ]);

        $auditLogger->log(
            request: $request,
            category: 'visit',
            action: 'created',
            title: 'Visit created',
            description: $visit->chief_complaint,
            patient: $patient,
            visit: $visit,
            entity: $visit,
            metadata: [
                'visit_date' => $visit->visit_date?->toDateString(),
                'visit_type' => $visit->visit_type,
                'status' => $visit->status,
            ]
        );

        return new PatientVisitResource($visit);
    }

    public function show(Request $request, Patient $patient, PatientVisit $visit): PatientVisitResource
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        return new PatientVisitResource($visit);
    }

    public function update(
        UpdatePatientVisitRequest $request,
        Patient $patient,
        PatientVisit $visit,
        AuditLogger $auditLogger
    ): PatientVisitResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $visit->update([
            ...$request->validated(),
            'case_sections' => $request->validated('case_sections') ?? [],
        ]);

        $visit = $visit->fresh();

        $auditLogger->log(
            request: $request,
            category: 'visit',
            action: 'updated',
            title: 'Visit updated',
            description: $visit->chief_complaint,
            patient: $patient,
            visit: $visit,
            entity: $visit,
            metadata: [
                'visit_date' => $visit->visit_date?->toDateString(),
                'visit_type' => $visit->visit_type,
                'status' => $visit->status,
                'case_source' => $visit->case_source,
            ]
        );

        return new PatientVisitResource($visit);
    }

    public function destroy(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        AuditLogger $auditLogger
    ): JsonResponse
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $auditLogger->log(
            request: $request,
            category: 'visit',
            action: 'deleted',
            title: 'Visit deleted',
            description: $visit->chief_complaint,
            patient: $patient,
            visit: $visit,
            entity: $visit,
            metadata: [
                'visit_date' => $visit->visit_date?->toDateString(),
            ]
        );

        $visit->delete();

        return response()->json([
            'message' => 'Visit deleted successfully.',
        ]);
    }

    private function ensureCanAccessPatient(Request $request, Patient $patient): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        abort_unless($patient->doctor_id === $user->id, 403);
    }

    private function ensureCanAccessVisit(
        Request $request,
        Patient $patient,
        PatientVisit $visit
    ): void {
        $this->ensureCanAccessPatient($request, $patient);

        abort_unless($visit->patient_id === $patient->id, 404);

        if ($request->user()->role === 'admin') {
            return;
        }

        abort_unless($visit->doctor_id === $request->user()->id, 403);
    }
}
