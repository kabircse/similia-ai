<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientFollowUpSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_portal_invitation_id' => $this->patient_portal_invitation_id,
            'patient_id' => $this->patient_id,
            'source_patient_visit_id' => $this->source_patient_visit_id,
            'converted_patient_visit_id' => $this->converted_patient_visit_id,
            'doctor_id' => $this->doctor_id,
            'status' => $this->status,
            'response_language' => $this->response_language,
            'resolved_language' => $this->resolved_language,
            'overall_change' => $this->overall_change,
            'medicine_taken' => $this->medicine_taken,
            'main_changes' => $this->main_changes,
            'current_symptoms' => $this->current_symptoms,
            'new_symptoms' => $this->new_symptoms,
            'aggravation_notes' => $this->aggravation_notes,
            'other_medicines' => $this->other_medicines,
            'general_notes' => $this->general_notes,
            'red_flag_notes' => $this->red_flag_notes,
            'patient_questions' => $this->patient_questions,
            'general_energy' => $this->general_energy,
            'sleep' => $this->sleep,
            'appetite' => $this->appetite,
            'mood' => $this->mood,
            'preferred_contact_time' => $this->preferred_contact_time,
            'answers' => $this->answers ?? [],
            'detected_red_flags' => $this->detected_red_flags ?? [],
            'doctor_note' => $this->doctor_note,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'converted_at' => $this->converted_at?->toISOString(),
            'metadata' => $this->metadata ?? [],
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient?->id,
                'name' => $this->patient?->name,
                'age_years' => $this->patient?->age_years,
                'gender' => $this->patient?->gender,
                'phone' => $this->patient?->phone,
            ]),
            'source_visit' => $this->whenLoaded('sourceVisit', fn () => [
                'id' => $this->sourceVisit?->id,
                'visit_date' => $this->sourceVisit?->visit_date?->toDateString(),
                'visit_type' => $this->sourceVisit?->visit_type,
                'chief_complaint' => $this->sourceVisit?->chief_complaint,
            ]),
            'converted_visit' => $this->whenLoaded('convertedVisit', fn () => [
                'id' => $this->convertedVisit?->id,
                'visit_date' => $this->convertedVisit?->visit_date?->toDateString(),
                'visit_type' => $this->convertedVisit?->visit_type,
                'chief_complaint' => $this->convertedVisit?->chief_complaint,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
