<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RunWeightedRepertorizationRequest;
use App\Http\Resources\RepertorizationRunResource;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\RepertorizationRun;
use App\Services\Repertorization\CrossRepertorizationEngine;
use App\Services\Repertorization\EliminativeRepertorizationEngine;
use App\Services\Repertorization\WeightedRepertorizationEngine;
use Illuminate\Http\Request;

class RepertorizationController extends Controller
{
    public function index(Request $request, Patient $patient, PatientVisit $visit)
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $method = $request->query('method');

        $runs = RepertorizationRun::query()
            ->with(['results' => fn ($query) => $query->orderBy('rank')])
            ->where('patient_visit_id', $visit->id)
            ->when($method, fn($query) => $query->where('method', $method))
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return RepertorizationRunResource::collection($runs);
    }

    public function show(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        RepertorizationRun $run
    ): RepertorizationRunResource {
        $this->ensureCanAccessRun($request, $patient, $visit, $run);

        return new RepertorizationRunResource(
            $run->load(['results' => fn ($query) => $query->orderBy('rank')])
        );
    }

    public function runWeighted(
        RunWeightedRepertorizationRequest $request,
        Patient $patient,
        PatientVisit $visit,
        WeightedRepertorizationEngine $engine
    ): RepertorizationRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $run = $engine->run(
            visit: $visit,
            doctor: $request->user(),
            settings: $request->validated('settings') ?? []
        );

        return new RepertorizationRunResource($run->load('results'));
    }

    public function runCross(
        RunWeightedRepertorizationRequest $request,
        Patient $patient,
        PatientVisit $visit,
        CrossRepertorizationEngine $engine
    ): RepertorizationRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $run = $engine->run(
            visit: $visit,
            doctor: $request->user(),
            settings: $request->validated('settings') ?? []
        );

        return new RepertorizationRunResource($run->load('results'));
    }

    public function runEliminative(
        RunWeightedRepertorizationRequest $request,
        Patient $patient,
        PatientVisit $visit,
        EliminativeRepertorizationEngine $engine
    ): RepertorizationRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $run = $engine->run(
            visit: $visit,
            doctor: $request->user(),
            settings: $request->validated('settings') ?? []
        );

        return new RepertorizationRunResource($run->load('results'));
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

    private function ensureCanAccessRun(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        RepertorizationRun $run
    ): void {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_unless($run->patient_visit_id === $visit->id, 404);

        if ($request->user()->role === 'admin') {
            return;
        }

        abort_unless($run->doctor_id === $request->user()->id, 403);
    }
}
