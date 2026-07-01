<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewPatientFollowUpSubmissionRequest;
use App\Http\Resources\PatientFollowUpSubmissionResource;
use App\Models\Patient;
use App\Models\PatientFollowUpSubmission;
use App\Models\PatientVisit;
use App\Services\Audit\AuditLogger;
use App\Services\PatientPortal\PatientPortalService;
use Illuminate\Http\Request;

class PatientFollowUpSubmissionController extends Controller
{
    public function index(Request $request, Patient $patient, PatientVisit $visit)
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $submissions = PatientFollowUpSubmission::query()
            ->with(['patient', 'sourceVisit', 'convertedVisit'])
            ->where('patient_id', $patient->id)
            ->where('source_patient_visit_id', $visit->id)
            ->latest('submitted_at')
            ->paginate($request->integer('per_page', 10));

        return PatientFollowUpSubmissionResource::collection($submissions);
    }

    public function show(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        PatientFollowUpSubmission $submission
    ): PatientFollowUpSubmissionResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);
        $this->ensureSubmissionBelongsToVisit($patient, $visit, $submission);

        return new PatientFollowUpSubmissionResource(
            $submission->load(['patient', 'sourceVisit', 'convertedVisit'])
        );
    }

    public function review(
        ReviewPatientFollowUpSubmissionRequest $request,
        Patient $patient,
        PatientVisit $visit,
        PatientFollowUpSubmission $submission,
        AuditLogger $auditLogger
    ): PatientFollowUpSubmissionResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);
        $this->ensureSubmissionBelongsToVisit($patient, $visit, $submission);

        $data = $request->validated();

        $submission->update([
            'status' => $data['status'],
            'doctor_note' => $data['doctor_note'] ?? null,
            'reviewed_at' => now(),
        ]);

        $submission = $submission->fresh(['patient', 'sourceVisit', 'convertedVisit']);

        $auditLogger->log(
            request: $request,
            category: 'patient_portal',
            action: 'reviewed_patient_follow_up_submission',
            title: 'Patient follow-up submission reviewed',
            description: $submission->doctor_note,
            patient: $patient,
            visit: $visit,
            entity: $submission,
            metadata: [
                'status' => $submission->status,
                'overall_change' => $submission->overall_change,
            ]
        );

        return new PatientFollowUpSubmissionResource($submission);
    }

    public function convertToVisit(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        PatientFollowUpSubmission $submission,
        PatientPortalService $service,
        AuditLogger $auditLogger
    ): PatientFollowUpSubmissionResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);
        $this->ensureSubmissionBelongsToVisit($patient, $visit, $submission);

        $newVisit = $service->convertToVisit($submission);
        $submission = $submission->fresh(['patient', 'sourceVisit', 'convertedVisit']);

        $auditLogger->log(
            request: $request,
            category: 'patient_portal',
            action: 'converted_patient_follow_up_submission',
            title: 'Patient follow-up submission converted to visit',
            description: "Created follow-up visit #{$newVisit->id}.",
            patient: $patient,
            visit: $visit,
            entity: $submission,
            metadata: [
                'converted_patient_visit_id' => $newVisit->id,
                'overall_change' => $submission->overall_change,
            ]
        );

        return new PatientFollowUpSubmissionResource($submission);
    }

    private function ensureCanAccessVisit(Request $request, Patient $patient, PatientVisit $visit): void
    {
        abort_unless($visit->patient_id === $patient->id, 404);

        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        abort_unless($patient->doctor_id === $user->id, 403);
        abort_unless($visit->doctor_id === $user->id, 403);
    }

    private function ensureSubmissionBelongsToVisit(
        Patient $patient,
        PatientVisit $visit,
        PatientFollowUpSubmission $submission
    ): void {
        abort_unless($submission->patient_id === $patient->id, 404);
        abort_unless($submission->source_patient_visit_id === $visit->id, 404);
    }
}
