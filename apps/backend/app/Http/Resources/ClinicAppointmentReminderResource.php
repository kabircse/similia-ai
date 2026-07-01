<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicAppointmentReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_appointment_id' => $this->clinic_appointment_id,
            'doctor_id' => $this->doctor_id,
            'patient_id' => $this->patient_id,
            'reminder_type' => $this->reminder_type,
            'channel' => $this->channel,
            'status' => $this->status,
            'minutes_before' => $this->minutes_before,
            'due_at' => $this->due_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'title' => $this->title,
            'message' => $this->message,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
