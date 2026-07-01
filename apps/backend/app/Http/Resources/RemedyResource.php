<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RemedyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'abbreviation' => $this->abbreviation,
            'source' => $this->source,
            'external_id' => $this->external_id,
            'is_active' => $this->is_active,
            'aliases' => $this->whenLoaded('aliases', fn () => $this->aliases->map(fn ($alias) => [
                'id' => $alias->id,
                'alias' => $alias->alias,
                'alias_type' => $alias->alias_type,
                'source' => $alias->source,
            ])->values()),
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
