<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseRubricResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_visit_id' => $this->patient_visit_id,
            'repertory_rubric_id' => $this->repertory_rubric_id,
            'doctor_id' => $this->doctor_id,
            'symptom_type' => $this->symptom_type,
            'importance' => $this->importance,
            'weight' => $this->weight,
            'is_essential' => $this->is_essential,
            'note' => $this->note,
            'rubric' => new RepertoryRubricResource($this->whenLoaded('rubric')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}