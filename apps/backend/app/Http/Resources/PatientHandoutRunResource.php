<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientHandoutRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'patient_id' => $this->patient_id,
            'patient_visit_id' => $this->patient_visit_id,
            'doctor_id' => $this->doctor_id,

            'prescription_id' => $this->prescription_id,
            'prescription_review_run_id' => $this->prescription_review_run_id,

            'status' => $this->status,
            'handout_type' => $this->handout_type,

            'response_language' => $this->response_language,
            'resolved_language' => $this->resolved_language,

            'title' => $this->title,

            'patient_summary' => $this->patient_summary,
            'medicine_instruction' => $this->medicine_instruction,
            'diet_lifestyle_instruction' => $this->diet_lifestyle_instruction,
            'follow_up_instruction' => $this->follow_up_instruction,
            'warning_instruction' => $this->warning_instruction,

            'case_snapshot' => $this->case_snapshot ?? [],
            'prescription_snapshot' => $this->prescription_snapshot ?? [],
            'clinic_snapshot' => $this->clinic_snapshot ?? [],
            'review_snapshot' => $this->review_snapshot ?? [],

            'warning_signs' => $this->warning_signs ?? [],
            'do_and_dont' => $this->do_and_dont ?? [],

            'footer_note' => $this->footer_note,
            'safety_note' => $this->safety_note,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata ?? [],

            'sections' => PatientHandoutSectionResource::collection(
                $this->whenLoaded('sections')
            ),

            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'printed_at' => $this->printed_at?->toISOString(),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
