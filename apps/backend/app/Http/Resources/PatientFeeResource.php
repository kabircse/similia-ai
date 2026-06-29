<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientFeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'patient_visit_id' => $this->patient_visit_id,
            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,

            'currency' => $this->currency,

            'consultation_fee' => $this->consultation_fee,
            'medicine_fee' => $this->medicine_fee,
            'discount_amount' => $this->discount_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'due_amount' => $this->due_amount,

            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'payment_date' => $this->payment_date?->toDateString(),

            'note' => $this->note,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
