<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FollowUpProgressItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'follow_up_analysis_run_id' => $this->follow_up_analysis_run_id,
            'patient_id' => $this->patient_id,
            'patient_visit_id' => $this->patient_visit_id,

            'category' => $this->category,
            'symptom' => $this->symptom,
            'change_status' => $this->change_status,

            'previous_intensity' => $this->previous_intensity,
            'current_intensity' => $this->current_intensity,
            'change_score' => $this->change_score,

            'evidence' => $this->evidence,
            'metadata' => $this->metadata ?? [],

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
