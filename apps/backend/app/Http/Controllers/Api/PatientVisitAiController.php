<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StructurePatientVisitRequest;
use App\Http\Resources\PatientVisitResource;
use App\Models\Patient;
use App\Models\PatientVisit;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PatientVisitAiController extends Controller
{
    public function structure(
        StructurePatientVisitRequest $request,
        Patient $patient,
        PatientVisit $visit
    ): PatientVisitResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $existingSections = $visit->case_sections ?? [];

        if ($existingSections === []) {
            $existingSections = new \stdClass();
        }

        $payload = [
            'raw_text' => $request->input('raw_case_text', $visit->raw_case_text ?? ''),
            'chief_complaint' => $request->input('chief_complaint', $visit->chief_complaint),
            'existing_case_sections' => $existingSections,
        ];

        try {
            $response = Http::timeout(config('services.ai_service.timeout'))
                ->acceptJson()
                ->post(rtrim(config('services.ai_service.url'), '/') . '/case/structure', $payload);
        } catch (ConnectionException) {
            abort(502, 'AI service is not reachable. Please make sure FastAPI is running on port 8001.');
        }

        if ($response->failed()) {
            abort(502, 'AI service failed to structure the case.');
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            abort(502, 'AI service returned an invalid response.');
        }

        $overwrite = $request->boolean('overwrite_existing_sections', false);

        $visit->update([
            'case_source' => 'mixed',
            'chief_complaint' => $data['chief_complaint'] ?? $visit->chief_complaint,
            'case_sections' => $this->mergeCaseSections(
                $visit->case_sections ?? [],
                $data['case_sections'] ?? [],
                $overwrite
            ),
            'missing_questions' => $data['missing_questions'] ?? [],
            'red_flags' => $data['red_flags'] ?? [],
        ]);

        return new PatientVisitResource($visit->fresh());
    }

    private function mergeCaseSections(array $existing, array $incoming, bool $overwrite): array
    {
        $merged = $existing;

        foreach ($incoming as $key => $value) {
            $value = is_string($value) ? trim($value) : $value;

            if ($value === '' || $value === null) {
                continue;
            }

            if ($overwrite || empty($merged[$key])) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    private function ensureCanAccessVisit(
        StructurePatientVisitRequest $request,
        Patient $patient,
        PatientVisit $visit
    ): void {
        $user = $request->user();

        if ($user->role !== 'admin') {
            abort_unless($patient->doctor_id === $user->id, 403);
            abort_unless($visit->doctor_id === $user->id, 403);
        }

        abort_unless($visit->patient_id === $patient->id, 404);
    }
}