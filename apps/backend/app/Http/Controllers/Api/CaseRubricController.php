<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCaseRubricRequest;
use App\Http\Requests\UpdateCaseRubricRequest;
use App\Http\Resources\CaseRubricResource;
use App\Models\CaseRubric;
use App\Models\Patient;
use App\Models\PatientVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseRubricController extends Controller
{
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
        PatientVisit $visit
    ): CaseRubricResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $caseRubric = CaseRubric::updateOrCreate(
            [
                'patient_visit_id' => $visit->id,
                'repertory_rubric_id' => $request->validated('repertory_rubric_id'),
            ],
            [
                'doctor_id' => $request->user()->id,
                'symptom_type' => $request->validated('symptom_type'),
                'importance' => $request->validated('importance'),
                'weight' => $request->validated('weight'),
                'is_essential' => $request->boolean('is_essential'),
                'note' => $request->validated('note'),
            ]
        );

        return new CaseRubricResource($caseRubric->load('rubric'));
    }

    public function update(
        UpdateCaseRubricRequest $request,
        Patient $patient,
        PatientVisit $visit,
        CaseRubric $caseRubric
    ): CaseRubricResource {
        $this->ensureCanAccessCaseRubric($request, $patient, $visit, $caseRubric);

        $caseRubric->update($request->validated());

        return new CaseRubricResource($caseRubric->fresh()->load('rubric'));
    }

    public function destroy(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        CaseRubric $caseRubric
    ): JsonResponse {
        $this->ensureCanAccessCaseRubric($request, $patient, $visit, $caseRubric);

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