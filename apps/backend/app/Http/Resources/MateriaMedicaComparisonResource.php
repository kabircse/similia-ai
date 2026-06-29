<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MateriaMedicaComparisonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this->resource['summary'] ?? null,
            'remedies' => $this->resource['remedies'] ?? [],
            'safety_note' => $this->resource['safety_note'] ?? null,
            'engine' => $this->resource['engine'] ?? null,
        ];
    }
}
