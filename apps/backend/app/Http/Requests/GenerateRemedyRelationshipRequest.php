<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateRemedyRelationshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'primary_remedy_id' => ['nullable', 'integer', 'exists:remedies,id'],
            'primary_remedy_code' => ['nullable', 'string', 'max:80'],
            'primary_remedy_name' => ['nullable', 'string', 'max:255'],
            'comparison_remedy_id' => ['nullable', 'integer', 'exists:remedies,id'],
            'comparison_remedy_code' => ['nullable', 'string', 'max:80'],
            'comparison_remedy_name' => ['nullable', 'string', 'max:255'],
            'purpose' => [
                'nullable',
                Rule::in([
                    'general',
                    'before_prescription',
                    'follow_up',
                    'change_remedy',
                    'antidote_check',
                    'compare',
                ]),
            ],
            'prescription_id' => ['nullable', 'integer', 'exists:patient_prescriptions,id'],
            'include_visit_context' => ['nullable', 'boolean'],
            'include_follow_up_context' => ['nullable', 'boolean'],
            'response_language' => ['nullable', 'string', 'in:auto,bn-BD,en-US,hi-IN'],
        ];
    }
}
