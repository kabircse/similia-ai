<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientPrescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'patient_visit_id' => $this->patient_visit_id,
            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,

            'repertorization_run_id' => $this->repertorization_run_id,
            'repertorization_result_id' => $this->repertorization_result_id,
            'remedy_id' => $this->remedy_id,
            'source_method' => $this->source_method,

            'remedy_code' => $this->remedy_code,
            'remedy_name' => $this->remedy_name,
            'potency' => $this->potency,
            'repetition' => $this->repetition,

            'dose_instruction' => $this->dose_instruction,
            'reason' => $this->reason,
            'advice' => $this->advice,
            'food_lifestyle_note' => $this->food_lifestyle_note,

            'follow_up_date' => $this->follow_up_date?->toDateString(),

            'status' => $this->status,
            'finalized_at' => $this->finalized_at?->toISOString(),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
