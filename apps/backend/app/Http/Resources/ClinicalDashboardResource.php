<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicalDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource ?? [];

        return [
            'filters' => $data['filters'] ?? [],
            'kpis' => $data['kpis'] ?? [],
            'clinic_activity' => $data['clinic_activity'] ?? [],
            'outcomes' => $data['outcomes'] ?? [],
            'remedies' => $data['remedies'] ?? [],
            'safety' => $data['safety'] ?? [],
            'finance' => $data['finance'] ?? [],
            'follow_ups' => $data['follow_ups'] ?? [],
            'recent_alerts' => $data['recent_alerts'] ?? [],
            'generated_at' => now()->toISOString(),
        ];
    }
}
