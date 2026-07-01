<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PotencyGuidanceOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'potency_guidance_run_id' => $this->potency_guidance_run_id,
            'potency_range' => $this->potency_range,
            'potency_label' => $this->potency_label,
            'rank' => $this->rank,
            'suitability_score' => $this->suitability_score,
            'rationale' => $this->rationale,
            'repetition_note' => $this->repetition_note,
            'caution' => $this->caution,
            'source_chunks' => $this->source_chunks ?? [],
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
