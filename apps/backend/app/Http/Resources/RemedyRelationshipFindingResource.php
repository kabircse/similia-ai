<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RemedyRelationshipFindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'remedy_relationship_run_id' => $this->remedy_relationship_run_id,
            'related_remedy_id' => $this->related_remedy_id,
            'related_remedy_code' => $this->related_remedy_code,
            'related_remedy_name' => $this->related_remedy_name,
            'relationship_type' => $this->relationship_type,
            'direction' => $this->direction,
            'rank' => $this->rank,
            'confidence_score' => $this->confidence_score,
            'summary' => $this->summary,
            'clinical_note' => $this->clinical_note,
            'caution' => $this->caution,
            'evidence' => $this->evidence ?? [],
            'source_chunks' => $this->source_chunks ?? [],
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
