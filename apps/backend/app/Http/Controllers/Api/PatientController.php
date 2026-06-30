<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));

        $patients = Patient::query()
            ->when($user->role !== 'admin', function ($query) use ($user) {
                $query->where('doctor_id', $user->id);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return PatientResource::collection($patients);
    }

    public function store(StorePatientRequest $request, AuditLogger $auditLogger): PatientResource
    {
        $patient = Patient::create([
            ...$request->validated(),
            'doctor_id' => $request->user()->id,
        ]);

        $auditLogger->log(
            request: $request,
            category: 'patient',
            action: 'created',
            title: 'Patient created',
            description: $patient->name,
            patient: $patient,
            entity: $patient,
            metadata: [
                'patient_name' => $patient->name,
                'phone' => $patient->phone,
            ]
        );

        return new PatientResource($patient);
    }

    public function show(Request $request, Patient $patient): PatientResource
    {
        $this->ensureCanAccessPatient($request, $patient);

        return new PatientResource($patient);
    }

    public function update(
        UpdatePatientRequest $request,
        Patient $patient,
        AuditLogger $auditLogger
    ): PatientResource
    {
        $this->ensureCanAccessPatient($request, $patient);

        $safeFields = [
            'name',
            'age_years',
            'gender',
            'phone',
            'address',
            'occupation',
            'marital_status',
        ];
        $before = $patient->only($safeFields);

        $patient->update($request->validated());

        $patient = $patient->fresh();
        $after = $patient->only($safeFields);

        $auditLogger->log(
            request: $request,
            category: 'patient',
            action: 'updated',
            title: 'Patient updated',
            description: $patient->name,
            patient: $patient,
            entity: $patient,
            before: $before,
            after: $after
        );

        return new PatientResource($patient);
    }

    public function destroy(Request $request, Patient $patient, AuditLogger $auditLogger): JsonResponse
    {
        $this->ensureCanAccessPatient($request, $patient);

        $auditLogger->log(
            request: $request,
            category: 'patient',
            action: 'deleted',
            title: 'Patient deleted',
            description: $patient->name,
            patient: $patient,
            entity: $patient,
            metadata: [
                'patient_name' => $patient->name,
                'phone' => $patient->phone,
            ]
        );

        $patient->delete();

        return response()->json([
            'message' => 'Patient deleted successfully.',
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
}
