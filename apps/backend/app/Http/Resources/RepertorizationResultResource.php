<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepertorizationResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'repertorization_run_id' => $this->repertorization_run_id,
            'remedy_code' => $this->remedy_code,
            'remedy_name' => $this->remedy_name,
            'total_score' => $this->total_score,
            'rubric_coverage' => $this->rubric_coverage,
            'essential_coverage' => $this->essential_coverage,
            'rank' => $this->rank,
            'supporting_rubrics' => $this->supporting_rubrics ?? [],
            'missing_important_rubrics' => $this->missing_important_rubrics ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}