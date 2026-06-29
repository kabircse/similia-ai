<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,

            'visit_date' => $this->visit_date?->toDateString(),
            'visit_type' => $this->visit_type,
            'status' => $this->status,
            'case_source' => $this->case_source,

            'chief_complaint' => $this->chief_complaint,
            'raw_case_text' => $this->raw_case_text,
            'case_sections' => $this->case_sections ?? [],

            'missing_questions' => $this->missing_questions ?? [],
            'red_flags' => $this->red_flags ?? [],

            'doctor_notes' => $this->doctor_notes,
            'next_follow_up_date' => $this->next_follow_up_date?->toDateString(),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}