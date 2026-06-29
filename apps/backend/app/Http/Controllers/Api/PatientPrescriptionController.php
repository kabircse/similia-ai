<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePatientPrescriptionRequest;
use App\Http\Resources\PatientPrescriptionResource;
use App\Models\Patient;
use App\Models\PatientPrescription;
use App\Models\PatientVisit;
use App\Models\RepertorizationResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientPrescriptionController extends Controller
{
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
        PatientVisit $visit
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

        $data['patient_id'] = $patient->id;
        $data['doctor_id'] = $request->user()->id;

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

        return new PatientPrescriptionResource($prescription->fresh());
    }

    public function destroy(Request $request, Patient $patient, PatientVisit $visit): JsonResponse
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $prescription = PatientPrescription::query()
            ->where('patient_visit_id', $visit->id)
            ->first();

        if ($prescription) {
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
