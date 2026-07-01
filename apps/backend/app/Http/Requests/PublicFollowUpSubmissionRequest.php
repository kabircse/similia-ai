<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicFollowUpSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'overall_change' => [
                'required',
                Rule::in(['improved', 'worse', 'same', 'mixed', 'unsure']),
            ],
            'medicine_taken' => ['nullable', 'boolean'],
            'main_changes' => ['nullable', 'string', 'max:10000'],
            'current_symptoms' => ['nullable', 'string', 'max:10000'],
            'new_symptoms' => ['nullable', 'string', 'max:10000'],
            'aggravation_notes' => ['nullable', 'string', 'max:10000'],
            'other_medicines' => ['nullable', 'string', 'max:5000'],
            'general_notes' => ['nullable', 'string', 'max:10000'],
            'red_flag_notes' => ['nullable', 'string', 'max:5000'],
            'patient_questions' => ['nullable', 'string', 'max:5000'],
            'general_energy' => ['nullable', 'string', 'max:100'],
            'sleep' => ['nullable', 'string', 'max:100'],
            'appetite' => ['nullable', 'string', 'max:100'],
            'mood' => ['nullable', 'string', 'max:100'],
            'preferred_contact_time' => ['nullable', 'string', 'max:255'],
            'consent_to_submit' => ['accepted'],
        ];
    }
}
