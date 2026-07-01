<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepertoryRubricResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'repertory_source_id' => $this->repertory_source_id,
            'external_id' => $this->external_id,
            'external_repertory_id' => $this->external_repertory_id,
            'source' => $this->source,
            'chapter' => $this->chapter,
            'rubric_path' => $this->rubric_path,
            'rubric_text' => $this->rubric_text,
            'medicine_count' => $this->medicine_count,
            'default_weight' => $this->default_weight,
            'is_selectable' => $this->is_selectable,
            'page' => $this->page,
            'remedies_count' => $this->whenCounted('remedies'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
