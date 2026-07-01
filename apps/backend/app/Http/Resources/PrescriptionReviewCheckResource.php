<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrescriptionReviewCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prescription_review_run_id' => $this->prescription_review_run_id,
            'doctor_id' => $this->doctor_id,
            'check_key' => $this->check_key,
            'category' => $this->category,
            'severity' => $this->severity,
            'status' => $this->status,
            'is_required' => $this->is_required,
            'is_blocking' => $this->is_blocking,
            'title' => $this->title,
            'description' => $this->description,
            'ai_assessment' => $this->ai_assessment,
            'doctor_note' => $this->doctor_note,
            'doctor_confirmed_at' => $this->doctor_confirmed_at?->toISOString(),
            'evidence' => $this->evidence ?? [],
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
