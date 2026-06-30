<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateRemedySuggestionRequest;
use App\Http\Resources\RemedySuggestionRunResource;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\RemedySuggestionRun;
use App\Services\Audit\AuditLogger;
use App\Services\Suggestions\RemedySuggestionGenerator;
use Illuminate\Http\Request;

class RemedySuggestionController extends Controller
{
    public function index(Request $request, Patient $patient, PatientVisit $visit)
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $runs = RemedySuggestionRun::query()
            ->with(['items' => fn ($query) => $query->orderBy('rank')])
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return RemedySuggestionRunResource::collection($runs);
    }

    public function show(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        RemedySuggestionRun $suggestionRun
    ): RemedySuggestionRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        abort_unless($suggestionRun->patient_visit_id === $visit->id, 404);

        return new RemedySuggestionRunResource(
            $suggestionRun->load(['items' => fn ($query) => $query->orderBy('rank')])
        );
    }

    public function generate(
        GenerateRemedySuggestionRequest $request,
        Patient $patient,
        PatientVisit $visit,
        RemedySuggestionGenerator $generator,
        AuditLogger $auditLogger
    ): RemedySuggestionRunResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $validated = $request->validated();
        $settings = [
            'include_potency' => $request->boolean('include_potency', true),
            'include_relationship' => $request->boolean('include_relationship', true),
            'include_medical_safety' => $request->boolean('include_medical_safety', true),
            'include_organon' => $request->boolean('include_organon', true),
        ];

        $run = $generator->generate(
            patient: $patient,
            visit: $visit,
            doctorId: $request->user()->id,
            repertorizationRunId: $validated['repertorization_run_id'] ?? null,
            method: $validated['method'] ?? null,
            limit: $validated['limit'] ?? 3,
            settings: $settings
        );

        $auditLogger->log(
            request: $request,
            category: 'ai',
            action: 'generated_remedy_suggestion',
            title: 'AI remedy suggestion generated',
            description: 'Generated remedy suggestions from imported repertory, materia medica, and knowledge chunks.',
            patient: $patient,
            visit: $visit,
            entity: $run,
            metadata: [
                'method' => $run->method,
                'suggestion_run_id' => $run->id,
                'items_count' => $run->items()->count(),
            ]
        );

        return new RemedySuggestionRunResource(
            $run->load(['items' => fn ($query) => $query->orderBy('rank')])
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
