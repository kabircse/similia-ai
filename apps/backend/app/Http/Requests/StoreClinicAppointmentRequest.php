<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'patient_visit_id' => ['nullable', 'integer', 'exists:patient_visits,id'],
            'prescription_id' => ['nullable', 'integer', 'exists:patient_prescriptions,id'],
            'appointment_type' => [
                'nullable',
                Rule::in(['initial', 'follow_up', 'phone_follow_up', 'portal_review', 'medicine_pickup', 'other']),
            ],
            'source' => [
                'nullable',
                Rule::in(['manual', 'prescription_follow_up', 'patient_portal', 'review_queue']),
            ],
            'scheduled_start_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:360'],
            'timezone' => ['nullable', 'string', 'max:80'],
            'title' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'doctor_note' => ['nullable', 'string', 'max:5000'],
            'patient_instruction' => ['nullable', 'string', 'max:5000'],
            'contact_method' => [
                'nullable',
                Rule::in(['phone', 'whatsapp', 'sms', 'email', 'in_person']),
            ],
            'send_reminders' => ['nullable', 'boolean'],
            'reminder_minutes_before' => ['nullable', 'array'],
            'reminder_minutes_before.*' => ['integer', 'min:5', 'max:10080'],
        ];
    }
}
