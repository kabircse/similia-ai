<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesDoctorOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\SavePatientPrescriptionRequest;
use App\Http\Resources\PatientPrescriptionResource;
use App\Models\Patient;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\RepertorizationResult;
use App\Services\Audit\AuditLogger;
use App\Services\Remedies\RemedyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientPrescriptionController extends Controller
{
    use ResolvesDoctorOwnership;

    public function show(Request $request, Patient $patient, PatientVisit $visit): JsonResponse
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $prescription = PatientPrescription::query()
            ->where('patient_visit_id', $visit->id)
            ->first();

        return response()->json([
            'data' => $prescription
                ? new PatientPrescriptionResource($prescription)
                : null,
        ]);
    }

    public function save(
        SavePatientPrescriptionRequest $request,
        Patient $patient,
        PatientVisit $visit,
        RemedyResolver $remedyResolver,
        AuditLogger $auditLogger
    ): PatientPrescriptionResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $data = $request->validated();

        $existing = PatientPrescription::query()
            ->where('patient_visit_id', $visit->id)
            ->first();

        if (! empty($data['repertorization_result_id'])) {
            $result = RepertorizationResult::query()
                ->with('run')
                ->findOrFail($data['repertorization_result_id']);

            abort_unless($result->run?->patient_visit_id === $visit->id, 422);

            $data['repertorization_run_id'] = $result->repertorization_run_id;
            $data['source_method'] = $result->run?->method;
            $data['remedy_code'] = $result->remedy_code;
            $data['remedy_name'] = $result->remedy_name;
        }

        if (empty($data['source_method'])) {
            $data['source_method'] = 'manual';
        }

        $resolvedRemedy = $remedyResolver->findByText($data['remedy_code'] ?? null)
            ?: $remedyResolver->findByText($data['remedy_name'] ?? null);

        $data['remedy_id'] = $resolvedRemedy?->id;

        if (empty($data['remedy_code']) && $resolvedRemedy) {
            $data['remedy_code'] = $resolvedRemedy->code;
        }

        if (empty($data['remedy_name']) && $resolvedRemedy) {
            $data['remedy_name'] = $resolvedRemedy->name;
        }

        $data['patient_id'] = $patient->id;
        $data['doctor_id'] = $this->ownerDoctorIdForVisit($request, $visit);

        if ($data['status'] === 'final') {
            $data['finalized_at'] = $existing?->finalized_at ?? now();
        } else {
            $data['finalized_at'] = null;
        }

        $prescription = PatientPrescription::updateOrCreate(
            [
                'patient_visit_id' => $visit->id,
            ],
            $data
        );

        $prescription = $prescription->fresh();

        $auditLogger->log(
            request: $request,
            category: 'prescription',
            action: 'saved',
            title: 'Prescription saved',
            description: trim($prescription->remedy_name.' '.$prescription->potency),
            patient: $patient,
            visit: $visit,
            entity: $prescription,
            metadata: [
                'remedy_name' => $prescription->remedy_name,
                'potency' => $prescription->potency,
                'status' => $prescription->status,
                'source_method' => $prescription->source_method,
                'follow_up_date' => $prescription->follow_up_date?->toDateString(),
            ]
        );

        return new PatientPrescriptionResource($prescription);
    }

    public function destroy(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        AuditLogger $auditLogger
    ): JsonResponse {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $prescription = PatientPrescription::query()
            ->where('patient_visit_id', $visit->id)
            ->first();

        if ($prescription) {
            $auditLogger->log(
                request: $request,
                category: 'prescription',
                action: 'deleted',
                title: 'Prescription deleted',
                description: $prescription->remedy_name,
                patient: $patient,
                visit: $visit,
                entity: $prescription
            );

            $prescription->delete();
        }

        return response()->json([
            'message' => 'Prescription deleted successfully.',
        ]);
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
