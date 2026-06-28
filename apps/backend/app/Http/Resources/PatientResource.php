<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doctor_id' => $this->doctor_id,
            'name' => $this->name,
            'age_years' => $this->age_years,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'address' => $this->address,
            'occupation' => $this->occupation,
            'marital_status' => $this->marital_status,
            'emergency_contact' => $this->emergency_contact,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}