<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseQuestionSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'patient_id' => $this->patient_id,
            'patient_visit_id' => $this->patient_visit_id,
            'doctor_id' => $this->doctor_id,

            'status' => $this->status,
            'language' => $this->language,
            'mode' => $this->mode,

            'total_questions' => $this->total_questions,
            'answered_questions' => $this->answered_questions,

            'case_snapshot' => $this->case_snapshot ?? [],
            'settings' => $this->settings ?? [],

            'messages' => CaseQuestionMessageResource::collection(
                $this->whenLoaded('messages')
            ),

            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
