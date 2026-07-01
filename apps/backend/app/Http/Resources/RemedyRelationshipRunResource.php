<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RemedyRelationshipRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_visit_id' => $this->patient_visit_id,
            'doctor_id' => $this->doctor_id,
            'primary_remedy_id' => $this->primary_remedy_id,
            'primary_remedy_code' => $this->primary_remedy_code,
            'primary_remedy_name' => $this->primary_remedy_name,
            'comparison_remedy_id' => $this->comparison_remedy_id,
            'comparison_remedy_code' => $this->comparison_remedy_code,
            'comparison_remedy_name' => $this->comparison_remedy_name,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'response_language' => $this->response_language,
            'case_snapshot' => $this->case_snapshot ?? [],
            'prescription_snapshot' => $this->prescription_snapshot ?? [],
            'follow_up_snapshot' => $this->follow_up_snapshot ?? [],
            'retrieved_sources' => $this->retrieved_sources ?? [],
            'settings' => $this->settings ?? [],
            'relationship_summary' => $this->relationship_summary,
            'sequence_guidance' => $this->sequence_guidance,
            'antidote_guidance' => $this->antidote_guidance,
            'inimical_warning' => $this->inimical_warning,
            'complementary_note' => $this->complementary_note,
            'cautions' => $this->cautions ?? [],
            'doctor_review_points' => $this->doctor_review_points ?? [],
            'suggested_questions' => $this->suggested_questions ?? [],
            'safety_note' => $this->safety_note,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata ?? [],
            'findings' => RemedyRelationshipFindingResource::collection(
                $this->whenLoaded('findings')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
