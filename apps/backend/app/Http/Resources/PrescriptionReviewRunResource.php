<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionReviewRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_visit_id' => $this->patient_visit_id,
            'doctor_id' => $this->doctor_id,
            'prescription_id' => $this->prescription_id,
            'remedy_id' => $this->remedy_id,
            'remedy_code' => $this->remedy_code,
            'remedy_name' => $this->remedy_name,
            'potency' => $this->potency,
            'repetition' => $this->repetition,
            'status' => $this->status,
            'review_status' => $this->review_status,
            'safety_score' => $this->safety_score,
            'response_language' => $this->response_language,
            'case_snapshot' => $this->case_snapshot ?? [],
            'prescription_snapshot' => $this->prescription_snapshot ?? [],
            'remedy_suggestion_snapshot' => $this->remedy_suggestion_snapshot ?? [],
            'potency_guidance_snapshot' => $this->potency_guidance_snapshot ?? [],
            'relationship_snapshot' => $this->relationship_snapshot ?? [],
            'follow_up_snapshot' => $this->follow_up_snapshot ?? [],
            'review_summary' => $this->review_summary,
            'decision_guidance' => $this->decision_guidance,
            'risk_summary' => $this->risk_summary,
            'red_flags' => $this->red_flags ?? [],
            'missing_information' => $this->missing_information ?? [],
            'doctor_review_points' => $this->doctor_review_points ?? [],
            'recommended_actions' => $this->recommended_actions ?? [],
            'safety_note' => $this->safety_note,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata ?? [],
            'checks' => PrescriptionReviewCheckResource::collection(
                $this->whenLoaded('checks')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
