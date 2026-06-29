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
            'source' => $this->source,
            'chapter' => $this->chapter,
            'rubric_path' => $this->rubric_path,
            'rubric_text' => $this->rubric_text,
            'page' => $this->page,
            'remedies_count' => $this->whenCounted('remedies'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}