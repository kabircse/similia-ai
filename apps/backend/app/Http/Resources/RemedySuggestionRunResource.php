<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RemedySuggestionRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_visit_id' => $this->patient_visit_id,
            'doctor_id' => $this->doctor_id,
            'repertorization_run_id' => $this->repertorization_run_id,
            'method' => $this->method,
            'status' => $this->status,
            'limit' => $this->limit,
            'case_snapshot' => $this->case_snapshot ?? [],
            'selected_rubrics_snapshot' => $this->selected_rubrics_snapshot ?? [],
            'retrieved_sources' => $this->retrieved_sources ?? [],
            'settings' => $this->settings ?? [],
            'safety_note' => $this->safety_note,
            'error_message' => $this->error_message,
            'items' => RemedySuggestionItemResource::collection(
                $this->whenLoaded('items')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
