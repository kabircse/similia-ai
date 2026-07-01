<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientHandoutSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'patient_handout_run_id' => $this->patient_handout_run_id,

            'section_key' => $this->section_key,
            'category' => $this->category,
            'sort_order' => $this->sort_order,

            'title' => $this->title,
            'content' => $this->content,
            'is_important' => $this->is_important,

            'metadata' => $this->metadata ?? [],

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
