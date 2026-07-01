<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicReportRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'created_by_id' => $this->created_by_id,
            'scope_doctor_id' => $this->scope_doctor_id,

            'report_type' => $this->report_type,
            'status' => $this->status,

            'response_language' => $this->response_language,
            'resolved_language' => $this->resolved_language,

            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),

            'title' => $this->title,

            'executive_summary' => $this->executive_summary,
            'clinical_activity_summary' => $this->clinical_activity_summary,
            'outcome_summary' => $this->outcome_summary,
            'remedy_summary' => $this->remedy_summary,
            'safety_summary' => $this->safety_summary,
            'finance_summary' => $this->finance_summary,
            'follow_up_summary' => $this->follow_up_summary,

            'key_metrics' => $this->key_metrics ?? [],
            'dashboard_snapshot' => $this->dashboard_snapshot ?? [],
            'recommendations' => $this->recommendations ?? [],
            'limitations' => $this->limitations ?? [],

            'safety_note' => $this->safety_note,
            'error_message' => $this->error_message,

            'exported_at' => $this->exported_at?->toISOString(),
            'printed_at' => $this->printed_at?->toISOString(),

            'metadata' => $this->metadata ?? [],

            'sections' => ClinicReportSectionResource::collection(
                $this->whenLoaded('sections')
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
