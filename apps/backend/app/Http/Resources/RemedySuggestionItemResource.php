<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RemedySuggestionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'remedy_id' => $this->remedy_id,
            'remedy_code' => $this->remedy_code,
            'remedy_name' => $this->remedy_name,
            'rank' => $this->rank,
            'confidence_score' => $this->confidence_score,
            'repertory_score' => $this->repertory_score,
            'materia_medica_score' => $this->materia_medica_score,
            'knowledge_score' => $this->knowledge_score,
            'summary' => $this->summary,
            'matching_points' => $this->matching_points ?? [],
            'differentiating_points' => $this->differentiating_points ?? [],
            'missing_questions' => $this->missing_questions ?? [],
            'evidence_matrix' => $this->evidence_matrix ?? [],
            'repertory_evidence' => $this->repertory_evidence ?? [],
            'materia_medica_evidence' => $this->materia_medica_evidence ?? [],
            'potency_considerations' => $this->potency_considerations ?? [],
            'relationship_notes' => $this->relationship_notes ?? [],
            'medical_safety_notes' => $this->medical_safety_notes ?? [],
            'source_chunks' => $this->source_chunks ?? [],
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
