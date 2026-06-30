<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseQuestionMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'case_question_session_id' => $this->case_question_session_id,
            'patient_id' => $this->patient_id,
            'patient_visit_id' => $this->patient_visit_id,
            'doctor_id' => $this->doctor_id,

            'parent_message_id' => $this->parent_message_id,

            'role' => $this->role,
            'message_type' => $this->message_type,
            'status' => $this->status,

            'question_key' => $this->question_key,
            'category' => $this->category,
            'importance' => $this->importance,

            'content' => $this->content,

            'extracted_update' => $this->extracted_update ?? [],
            'metadata' => $this->metadata ?? [],

            'answered_at' => $this->answered_at?->toISOString(),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
