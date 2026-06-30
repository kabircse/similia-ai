<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AiTaskResource;
use App\Jobs\CompareMateriaMedicaJob;
use App\Jobs\StructureCaseJob;
use App\Models\AiTask;
use App\Models\Patient;
use App\Models\PatientVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiTaskController extends Controller
{
    public function show(Request $request, AiTask $aiTask): AiTaskResource
    {
        abort_unless(
            $aiTask->user_id === $request->user()->id || $request->user()->role === 'admin',
            403
        );

        return new AiTaskResource($aiTask);
    }

    public function structureCase(Request $request, Patient $patient, PatientVisit $visit): JsonResponse
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_if(blank($visit->raw_case_text), 422, 'Raw case text is required before AI structuring.');

        $task = AiTask::create([
            'user_id' => $request->user()->id,
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
            'type' => 'structure_case',
            'status' => 'queued',
            'title' => 'AI case structuring',
            'message' => 'Case structuring has been queued.',
            'progress' => 0,
            'payload' => [
                'visit_id' => $visit->id,
                'chief_complaint' => $visit->chief_complaint,
            ],
        ]);

        StructureCaseJob::dispatch($task->id);

        return (new AiTaskResource($task))->response()->setStatusCode(200);
    }

    public function compareMateriaMedica(Request $request, Patient $patient, PatientVisit $visit): JsonResponse
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $validated = $request->validate([
            'repertorization_run_id' => ['nullable', 'integer', 'exists:repertorization_runs,id'],
            'method' => ['nullable', 'string', 'in:weighted,cross,eliminative'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $task = AiTask::create([
            'user_id' => $request->user()->id,
            'patient_id' => $patient->id,
            'patient_visit_id' => $visit->id,
            'type' => 'compare_materia_medica',
            'status' => 'queued',
            'title' => 'Materia medica comparison',
            'message' => 'Materia medica comparison has been queued.',
            'progress' => 0,
            'payload' => [
                'repertorization_run_id' => $validated['repertorization_run_id'] ?? null,
                'method' => $validated['method'] ?? null,
                'limit' => $validated['limit'] ?? 3,
            ],
        ]);

        CompareMateriaMedicaJob::dispatch($task->id);

        return (new AiTaskResource($task))->response()->setStatusCode(200);
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
