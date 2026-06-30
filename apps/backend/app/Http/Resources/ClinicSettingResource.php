<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doctor_id' => $this->doctor_id,

            'clinic_name' => $this->clinic_name,
            'tagline' => $this->tagline,

            'doctor_display_name' => $this->doctor_display_name,
            'doctor_qualification' => $this->doctor_qualification,

            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'address' => $this->address,
            'logo_url' => $this->logo_url,

            'default_currency' => $this->default_currency,
            'default_consultation_fee' => $this->default_consultation_fee,
            'default_followup_fee' => $this->default_followup_fee,
            'medicine_fee_included' => $this->medicine_fee_included,

            'prescription_footer' => $this->prescription_footer,
            'case_sheet_footer' => $this->case_sheet_footer,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
