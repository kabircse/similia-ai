<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoiceTranscriptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'patient_id' => $this->patient_id,
            'patient_visit_id' => $this->patient_visit_id,
            'doctor_id' => $this->doctor_id,

            'language' => $this->language,
            'source' => $this->source,
            'status' => $this->status,

            'transcript_text' => $this->transcript_text,
            'segments' => $this->segments ?? [],

            'merged_to_case_text' => $this->merged_to_case_text,
            'merge_mode' => $this->merge_mode,

            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
