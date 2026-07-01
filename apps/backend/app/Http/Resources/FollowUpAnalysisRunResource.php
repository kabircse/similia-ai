<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FollowUpAnalysisRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'patient_id' => $this->patient_id,
            'patient_visit_id' => $this->patient_visit_id,
            'previous_visit_id' => $this->previous_visit_id,
            'doctor_id' => $this->doctor_id,
            'prescription_id' => $this->prescription_id,

            'status' => $this->status,
            'response_level' => $this->response_level,
            'progress_score' => $this->progress_score,

            'previous_case_snapshot' => $this->previous_case_snapshot ?? [],
            'current_case_snapshot' => $this->current_case_snapshot ?? [],
            'prescription_snapshot' => $this->prescription_snapshot ?? [],

            'analysis_summary' => $this->analysis_summary,
            'remedy_response_assessment' => $this->remedy_response_assessment,

            'improvement_points' => $this->improvement_points ?? [],
            'worsening_points' => $this->worsening_points ?? [],
            'unchanged_points' => $this->unchanged_points ?? [],
            'new_symptoms' => $this->new_symptoms ?? [],
            'old_symptoms_returned' => $this->old_symptoms_returned ?? [],
            'possible_aggravation_signs' => $this->possible_aggravation_signs ?? [],
            'red_flags' => $this->red_flags ?? [],

            'suggested_follow_up_questions' => $this->suggested_follow_up_questions ?? [],
            'doctor_review_points' => $this->doctor_review_points ?? [],
            'recommended_next_steps' => $this->recommended_next_steps ?? [],

            'safety_note' => $this->safety_note,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata ?? [],

            'progress_items' => FollowUpProgressItemResource::collection(
                $this->whenLoaded('progressItems')
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
