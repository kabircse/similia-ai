<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePatientHandoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'prescription_id' => [
                'nullable',
                'integer',
                'exists:patient_prescriptions,id',
            ],

            'prescription_review_run_id' => [
                'nullable',
                'integer',
                'exists:prescription_review_runs,id',
            ],

            'handout_type' => [
                'nullable',
                Rule::in(['prescription', 'follow_up', 'general_instruction']),
            ],

            'response_language' => [
                'nullable',
                'string',
                Rule::in(['auto', 'bn-BD', 'en-US', 'hi-IN']),
            ],

            'style' => [
                'nullable',
                Rule::in(['simple', 'detailed', 'minimal']),
            ],

            'include_clinic_branding' => ['nullable', 'boolean'],
            'include_warning_signs' => ['nullable', 'boolean'],
            'include_do_and_dont' => ['nullable', 'boolean'],
        ];
    }
}
