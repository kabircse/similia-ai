<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdvancedSearchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this['type'],
            'label' => $this['label'],

            'id' => $this['id'],
            'patient_id' => $this['patient_id'] ?? null,
            'patient_name' => $this['patient_name'] ?? null,
            'visit_id' => $this['visit_id'] ?? null,

            'title' => $this['title'],
            'subtitle' => $this['subtitle'] ?? null,
            'snippet' => $this['snippet'] ?? null,

            'url' => $this['url'] ?? null,
            'created_at' => $this['created_at'] ?? null,

            'score' => $this['score'] ?? 0,
            'metadata' => $this['metadata'] ?? [],
        ];
    }
}
