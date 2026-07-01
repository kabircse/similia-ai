<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicAppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_visit_id' => $this->patient_visit_id,
            'doctor_id' => $this->doctor_id,
            'prescription_id' => $this->prescription_id,
            'appointment_type' => $this->appointment_type,
            'source' => $this->source,
            'status' => $this->status,
            'scheduled_start_at' => $this->scheduled_start_at?->toISOString(),
            'scheduled_end_at' => $this->scheduled_end_at?->toISOString(),
            'timezone' => $this->timezone,
            'title' => $this->title,
            'reason' => $this->reason,
            'doctor_note' => $this->doctor_note,
            'patient_instruction' => $this->patient_instruction,
            'contact_method' => $this->contact_method,
            'send_reminders' => $this->send_reminders,
            'reminder_minutes_before' => $this->reminder_minutes_before ?? [],
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'no_show_at' => $this->no_show_at?->toISOString(),
            'metadata' => $this->metadata ?? [],
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient?->id,
                'name' => $this->patient?->name,
                'phone' => $this->patient?->phone,
            ]),
            'visit' => $this->whenLoaded('visit', fn () => [
                'id' => $this->visit?->id,
                'visit_date' => $this->visit?->visit_date?->toDateString(),
                'chief_complaint' => $this->visit?->chief_complaint,
            ]),
            'prescription' => $this->whenLoaded('prescription', fn () => [
                'id' => $this->prescription?->id,
                'remedy_name' => $this->prescription?->remedy_name,
                'potency' => $this->prescription?->potency,
                'follow_up_date' => $this->prescription?->follow_up_date?->toDateString(),
            ]),
            'reminders' => ClinicAppointmentReminderResource::collection(
                $this->whenLoaded('reminders')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
