<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
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

    public function store(StorePatientRequest $request): PatientResource
    {
        $patient = Patient::create([
            ...$request->validated(),
            'doctor_id' => $request->user()->id,
        ]);

        return new PatientResource($patient);
    }

    public function show(Request $request, Patient $patient): PatientResource
    {
        $this->ensureCanAccessPatient($request, $patient);

        return new PatientResource($patient);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): PatientResource
    {
        $this->ensureCanAccessPatient($request, $patient);

        $patient->update($request->validated());

        return new PatientResource($patient->fresh());
    }

    public function destroy(Request $request, Patient $patient): JsonResponse
    {
        $this->ensureCanAccessPatient($request, $patient);

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