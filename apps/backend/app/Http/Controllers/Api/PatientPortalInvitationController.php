<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientPortalInvitationRequest;
use App\Http\Resources\PatientPortalInvitationResource;
use App\Models\Patient;
use App\Models\PatientPortalInvitation;
use App\Models\PatientVisit;
use App\Services\Audit\AuditLogger;
use App\Services\PatientPortal\PatientPortalService;
use Illuminate\Http\Request;

class PatientPortalInvitationController extends Controller
{
    public function index(Request $request, Patient $patient, PatientVisit $visit)
    {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $invitations = PatientPortalInvitation::query()
            ->where('patient_id', $patient->id)
            ->where('patient_visit_id', $visit->id)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return PatientPortalInvitationResource::collection($invitations);
    }

    public function store(
        StorePatientPortalInvitationRequest $request,
        Patient $patient,
        PatientVisit $visit,
        PatientPortalService $service,
        AuditLogger $auditLogger
    ): PatientPortalInvitationResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);

        $data = $request->validated();

        $invitation = $service->createInvitation(
            patient: $patient,
            visit: $visit,
            doctorId: $request->user()->id,
            prescriptionId: $data['prescription_id'] ?? null,
            purpose: $data['purpose'] ?? 'follow_up_form',
            expiresInDays: $data['expires_in_days'] ?? 7,
            maxSubmissions: $data['max_submissions'] ?? 1,
            responseLanguage: $data['response_language'] ?? 'auto',
            messageToPatient: $data['message_to_patient'] ?? null
        );

        $auditLogger->log(
            request: $request,
            category: 'patient_portal',
            action: 'created_patient_portal_invitation',
            title: 'Patient portal invitation created',
            description: $invitation->purpose,
            patient: $patient,
            visit: $visit,
            entity: $invitation,
            metadata: [
                'purpose' => $invitation->purpose,
                'expires_at' => $invitation->expires_at?->toISOString(),
                'max_submissions' => $invitation->max_submissions,
                'response_language' => $invitation->response_language,
            ]
        );

        return new PatientPortalInvitationResource($invitation);
    }

    public function revoke(
        Request $request,
        Patient $patient,
        PatientVisit $visit,
        PatientPortalInvitation $invitation,
        PatientPortalService $service,
        AuditLogger $auditLogger
    ): PatientPortalInvitationResource {
        $this->ensureCanAccessVisit($request, $patient, $visit);
        $this->ensureInvitationBelongsToVisit($patient, $visit, $invitation);

        $invitation = $service->revoke($invitation);

        $auditLogger->log(
            request: $request,
            category: 'patient_portal',
            action: 'revoked_patient_portal_invitation',
            title: 'Patient portal invitation revoked',
            description: $invitation->purpose,
            patient: $patient,
            visit: $visit,
            entity: $invitation,
            metadata: [
                'public_id' => $invitation->public_id,
                'status' => $invitation->status,
            ]
        );

        return new PatientPortalInvitationResource($invitation);
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

    private function ensureInvitationBelongsToVisit(
        Patient $patient,
        PatientVisit $visit,
        PatientPortalInvitation $invitation
    ): void {
        abort_unless($invitation->patient_id === $patient->id, 404);
        abort_unless($invitation->patient_visit_id === $visit->id, 404);
    }
}
