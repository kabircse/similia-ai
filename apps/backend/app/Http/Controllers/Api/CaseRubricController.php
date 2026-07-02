<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesDoctorOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaseRubricRequest;
use App\Http\Requests\UpdateCaseRubricRequest;
use App\Http\Resources\CaseRubricResource;
use App\Models\CaseRubric;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseRubricController extends Controller
{
    use ResolvesDoctorOwnership;

    public function index(Request $request, Patient $patient, PatientVisit $visit)
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $caseRubrics = CaseRubric::query()
            ->with(['rubric'])
            ->where('patient_visit_id', $visit->id)
            ->orderByDesc('is_essential')
            ->orderByDesc('weight')
            ->latest()
            ->get();

        return CaseRubricResource::collection($caseRubrics);
    }

    public function store(
        StoreCaseRubricRequest $request,
        Patient $patient,
        PatientVisit $visit,
        AuditLogger $auditLogger
    ): CaseRubricResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $caseRubric = CaseRubric::updateOrCreate(
            [
                'patient_visit_id' => $visit->id,
                'repertory_rubric_id' => $request->validated('repertory_rubric_id'),
            ],
            [
                'doctor_id' => $this->ownerDoctorIdForVisit($request, $visit),
                'symptom_type' => $request->validated('symptom_type'),
                'importance' => $request->validated('importance'),
                'weight' => $request->validated('weight'),
                'is_essential' => $request->boolean('is_essential'),
                'note' => $request->validated('note'),
            ]
        );

        $caseRubric->load('rubric');

        $auditLogger->log(
            request: $request,
            category: 'rubric',
            action: 'selected',
            title: 'Rubric selected',
            description: $caseRubric->rubric?->rubric_path,
            patient: $patient,
            visit: $visit,
            entity: $caseRubric,
            metadata: [
                'rubric_path' => $caseRubric->rubric?->rubric_path,
                'symptom_type' => $caseRubric->symptom_type,
                'importance' => $caseRubric->importance,
                'weight' => $caseRubric->weight,
                'is_essential' => $caseRubric->is_essential,
            ]
        );

        return new CaseRubricResource($caseRubric->load('rubric'));
    }

    public function update(
        UpdateCaseRubricRequest $request,
        Patient $patient,
        PatientVisit $visit,
        CaseRubric $caseRubric,
        AuditLogger $auditLogger
    ): CaseRubricResource {
        $this->ensureCanAccessCaseRubric($request, $patient, $visit, $caseRubric);

        $caseRubric->update($request->validated());

        $caseRubric = $caseRubric->fresh()->load('rubric');

        $auditLogger->log(
            request: $request,
            category: 'rubric',
            action: 'updated',
            title: 'Rubric updated',
            description: $caseRubric->rubric?->rubric_path,
            patient: $patient,
            visit: $visit,
            entity: $caseRubric,
            metadata: [
                'rubric_path' => $caseRubric->rubric?->rubric_path,
                'symptom_type' => $caseRubric->symptom_type,
                'importance' => $caseRubric->importance,
                'weight' => $caseRubric->weight,
                'is_essential' => $caseRubric->is_essential,
            ]
        );

        return new CaseRubricResource($caseRubric);
    }

    public function destroy(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        CaseRubric $caseRubric,
        AuditLogger $auditLogger
    ): JsonResponse {
        $this->ensureCanAccessCaseRubric($request, $patient, $visit, $caseRubric);

        $caseRubric->load('rubric');

        $auditLogger->log(
            request: $request,
            category: 'rubric',
            action: 'deleted',
            title: 'Rubric removed',
            description: $caseRubric->rubric?->rubric_path,
            patient: $patient,
            visit: $visit,
            entity: $caseRubric
        );

        $caseRubric->delete();

        return response()->json([
            'message' => 'Rubric removed from case successfully.',
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

    private function ensureCanAccessCaseRubric(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        CaseRubric $caseRubric
    ): void {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_unless($caseRubric->patient_visit_id === $visit->id, 404);

        if ($request->user()->role === 'admin') {
            return;
        }

        abort_unless($caseRubric->doctor_id === $request->user()->id, 403);
    }
}
