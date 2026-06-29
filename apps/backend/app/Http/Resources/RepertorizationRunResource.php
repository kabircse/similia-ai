<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepertorizationRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_visit_id' => $this->patient_visit_id,
            'doctor_id' => $this->doctor_id,
            'method' => $this->method,
            'total_rubrics' => $this->total_rubrics,
            'essential_rubrics_count' => $this->essential_rubrics_count,
            'settings' => $this->settings ?? [],
            'selected_rubrics_snapshot' => $this->selected_rubrics_snapshot ?? [],
            'results' => RepertorizationResultResource::collection(
                $this->whenLoaded('results')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}