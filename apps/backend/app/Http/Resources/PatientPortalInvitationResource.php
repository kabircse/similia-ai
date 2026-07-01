<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientPortalInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'patient_id' => $this->patient_id,
            'patient_visit_id' => $this->patient_visit_id,
            'doctor_id' => $this->doctor_id,
            'prescription_id' => $this->prescription_id,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'response_language' => $this->response_language,
            'resolved_language' => $this->resolved_language,
            'max_submissions' => $this->max_submissions,
            'submission_count' => $this->submission_count,
            'opened_count' => $this->opened_count,
            'message_to_patient' => $this->message_to_patient,
            'portal_url' => $this->portalUrl(),
            'expires_at' => $this->expires_at?->toISOString(),
            'opened_at' => $this->opened_at?->toISOString(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'revoked_at' => $this->revoked_at?->toISOString(),
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
