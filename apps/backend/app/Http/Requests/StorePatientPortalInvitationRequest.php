<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientPortalInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'prescription_id' => ['nullable', 'integer', 'exists:patient_prescriptions,id'],
            'purpose' => [
                'nullable',
                Rule::in(['follow_up_form', 'post_prescription_check', 'general_update']),
            ],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'max_submissions' => ['nullable', 'integer', 'min:1', 'max:3'],
            'response_language' => [
                'nullable',
                'string',
                Rule::in(['auto', 'bn-BD', 'en-US', 'hi-IN']),
            ],
            'message_to_patient' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
