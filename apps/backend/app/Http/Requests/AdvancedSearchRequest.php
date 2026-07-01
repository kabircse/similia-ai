<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvancedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:255'],

            'types' => ['nullable', 'array'],
            'types.*' => [
                'string',
                Rule::in([
                    'patients',
                    'visits',
                    'prescriptions',
                    'remedy_suggestions',
                    'follow_up_analyses',
                    'potency_guidance',
                    'remedy_relationships',
                    'prescription_reviews',
                    'patient_handouts',
                    'clinic_reports',
                ]),
            ],

            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'visit_id' => ['nullable', 'integer', 'exists:patient_visits,id'],

            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],

            'limit' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
