<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PotencyGuidanceRunResource extends JsonResource
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
            'case_phase' => $this->case_phase,
            'status' => $this->status,
            'case_snapshot' => $this->case_snapshot ?? [],
            'prescription_snapshot' => $this->prescription_snapshot ?? [],
            'follow_up_snapshot' => $this->follow_up_snapshot ?? [],
            'retrieved_sources' => $this->retrieved_sources ?? [],
            'settings' => $this->settings ?? [],
            'vitality_level' => $this->vitality_level,
            'sensitivity_level' => $this->sensitivity_level,
            'pathology_depth' => $this->pathology_depth,
            'guidance_summary' => $this->guidance_summary,
            'repetition_guidance' => $this->repetition_guidance,
            'wait_and_watch_guidance' => $this->wait_and_watch_guidance,
            'aggravation_guidance' => $this->aggravation_guidance,
            'cautions' => $this->cautions ?? [],
            'follow_up_questions' => $this->follow_up_questions ?? [],
            'doctor_review_points' => $this->doctor_review_points ?? [],
            'safety_note' => $this->safety_note,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata ?? [],
            'options' => PotencyGuidanceOptionResource::collection(
                $this->whenLoaded('options')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
